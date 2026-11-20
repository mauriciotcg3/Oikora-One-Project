<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Application\Jobs\EnviarAviso;
use App\Modules\Avisos\Domain\EstadoEnvio;
use App\Modules\Avisos\Domain\JanelaDiurna;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

/**
 * Regras de canal por categoria (OIK-ADR-0008 §12, §14; RN-21): quais canais nascem com o aviso, qual é
 * o próximo no reenvio e como o envio nasce (`pendente` com job no commit, ou `agendado` para as 8h).
 * WhatsApp só pelo número ativo da organização; o da plataforma é só de códigos.
 */
final readonly class CaixaDeSaida
{
    public function __construct(
        private ConsultaOrganizacoes $organizacoes,
        private DestinosDeAviso $destinos,
        private NumerosWhatsApp $numeros,
        private PreferenciasDeAviso $preferencias,
    ) {}

    /**
     * Convite: todos os canais com contato. Serviço: o primeiro disponível na ordem da preferência. Sem
     * nenhum, o e-mail, que falha como `sem_destino` e deixa o motivo registrado.
     *
     * @return list<string>
     */
    public function canaisIniciais(int $organizacaoId, TipoAviso $tipo, ?int $contaId, ?int $pessoaId): array
    {
        $disponiveis = array_values(array_filter(
            $this->ordem($tipo, $contaId),
            fn (string $canal): bool => $this->disponivel($organizacaoId, $tipo, $canal, $contaId, $pessoaId),
        ));

        if ($disponiveis === []) {
            return ['email'];
        }

        return $tipo->enviaEmTodosOsCanais() ? $disponiveis : [$disponiveis[0]];
    }

    /** @param  list<string>  $jaTentados */
    public function proximoCanal(int $organizacaoId, TipoAviso $tipo, ?int $contaId, ?int $pessoaId, array $jaTentados): ?string
    {
        foreach ($this->ordem($tipo, $contaId) as $canal) {
            if (! in_array($canal, $jaTentados, true) && $this->disponivel($organizacaoId, $tipo, $canal, $contaId, $pessoaId)) {
                return $canal;
            }
        }

        return null;
    }

    /** Cria o envio (uma vez por aviso e canal). Fora da janela diurna, nasce `agendado` e o `envios:varrer` o solta. */
    public function criarEnvio(int $organizacaoId, int $avisoId, string $avisoCriadoEm, TipoAviso $tipo, string $canal): void
    {
        $agendadoPara = $tipo->esperaJanelaDiurna()
            ? JanelaDiurna::proximoEnvio(now()->toImmutable(), $this->organizacoes->perfilDeEnvio($organizacaoId)->fuso)
            : null;

        $envio = DB::selectOne(
            'insert into envios (organizacao_id, aviso_id, aviso_criado_em, canal, estado, agendado_para, enfileirado_em, request_id)
             values (?, ?, ?, ?, ?, ?, ?, ?)
             on conflict on constraint envios_aviso_canal_unico do nothing
             returning id',
            [
                $organizacaoId, $avisoId, $avisoCriadoEm, $canal,
                ($agendadoPara === null ? EstadoEnvio::Pendente : EstadoEnvio::Agendado)->value,
                $agendadoPara, $agendadoPara === null ? now() : null, Context::get('request_id'),
            ],
        );

        if ($envio !== null && $agendadoPara === null) {
            EnviarAviso::dispatch((int) $envio->id, $organizacaoId)->afterCommit();
        }
    }

    /** @return list<string> */
    private function ordem(TipoAviso $tipo, ?int $contaId): array
    {
        return $tipo->permiteEscolherCanal() && $contaId !== null
            ? $this->preferencias->ligados($contaId, $tipo)
            : $tipo->canais();
    }

    private function disponivel(int $organizacaoId, TipoAviso $tipo, string $canal, ?int $contaId, ?int $pessoaId): bool
    {
        if ($canal === 'whatsapp' && $this->numeros->daOrganizacao($organizacaoId) === null) {
            return false;
        }

        return $this->destinos->endereco($contaId, $pessoaId, $canal, ! $tipo->obrigatorio()) !== null;
    }
}
