<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Autorizacao\Contracts\QuemPode;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Contador `uso_mensageria` (OIK-ADR-0008 §16, §17): cada envio aceito pelo provedor soma 1 no mês
 * (fuso da organização), canal e categoria, com o custo estimado pela tabela de preços configurada.
 * No WhatsApp, cruzar 80% do limite avisa quem configura a organização, e 100% avisa que o corte
 * começou. Roda no contexto RLS do job de envio.
 */
final readonly class UsoDeMensageria
{
    public const ALERTA = 0.8;

    public function __construct(
        private LimiteDeWhatsApp $limite,
        private QuemPode $quemPode,
        private AcessosDaOrganizacao $acessos,
        private Avisos $avisos,
    ) {}

    public function contabilizar(int $organizacaoId, string $canal, TipoAviso $tipo): void
    {
        $mes = $this->limite->mes($organizacaoId);
        $custo = (float) config("oikora.avisos.precos.{$canal}.{$tipo->categoria()}", 0);

        DB::statement(
            'insert into uso_mensageria (organizacao_id, mes, canal, categoria, quantidade, custo_estimado, created_at, updated_at)
             values (?, ?, ?, ?, 1, ?, now(), now())
             on conflict (organizacao_id, mes, canal, categoria)
             do update set quantidade = uso_mensageria.quantidade + 1,
                           custo_estimado = uso_mensageria.custo_estimado + excluded.custo_estimado,
                           updated_at = now()',
            [$organizacaoId, $mes, $canal, $tipo->categoria(), $custo],
        );

        if ($canal === 'whatsapp') {
            $this->conferirLimite($organizacaoId, $mes);
        }
    }

    private function conferirLimite(int $organizacaoId, string $mes): void
    {
        $limite = $this->limite->limite($organizacaoId);
        if ($limite === null || $limite === 0) {
            return;
        }

        $usado = $this->limite->usadoNoMes($organizacaoId, $mes);
        $tipo = match (true) {
            $usado >= $limite => TipoAviso::LimiteWhatsappAtingido,
            $usado >= (int) ceil($limite * self::ALERTA) => TipoAviso::LimiteWhatsappAlerta,
            default => null,
        };

        if ($tipo === null) {
            return;
        }

        $contas = array_values(array_unique($this->acessos->contasAtivas(
            $this->quemPode->naOrganizacao(Permissao::OrganizacaoConfigurar, $organizacaoId),
        )));
        if ($contas === []) {
            return;
        }

        // Um aviso por mês e faixa: a idempotência do `avisos` impede repetir a cada envio.
        $this->avisos->registrar(
            $tipo,
            array_map(static fn (int $contaId): Destinatario => Destinatario::conta($contaId, null, '/painel/organizacao'), $contas),
            new Referencia('uso_mensageria', substr($mes, 0, 7)),
        );
        Log::info('avisos.limite_whatsapp', ['faixa' => $tipo->value, 'usado' => $usado, 'limite' => $limite]);
    }
}
