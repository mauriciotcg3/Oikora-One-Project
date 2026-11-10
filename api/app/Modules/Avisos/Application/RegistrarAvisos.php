<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Application\Jobs\EnviarAviso;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\EstadoEnvio;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Caixa de saída (OIK-ADR-0008 §2, §4; RN-21, RN-22; CA-25, CA-26). Aviso e envios entram na transação
 * do fato; o job é despachado com `afterCommit`, então nenhum provedor é chamado antes do commit e um
 * rollback não deixa nada. Se o processo cair entre o commit e o despacho, o `envios:varrer` recupera.
 */
final readonly class RegistrarAvisos implements Avisos
{
    public function __construct(private ConsultaOrganizacoes $organizacoes) {}

    public function registrar(TipoAviso $tipo, array $destinatarios, Referencia $referencia, string $chave = ''): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('avisos.exige_transacao');
        }

        $organizacaoId = (int) DB::scalar('select app_organizacao_atual()');
        $emMigracao = $this->organizacoes->emMigracao($organizacaoId);

        foreach ($destinatarios as $destinatario) {
            $agora = Carbon::now();
            $aviso = DB::selectOne(
                'insert into avisos (uuid, organizacao_id, tipo, conta_id, pessoa_id, destinatario, referencia_tipo, referencia_id, chave, destino, created_at, updated_at)
                 values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 on conflict on constraint avisos_idempotencia do nothing
                 returning id, created_at',
                [
                    (string) Str::uuid(), $organizacaoId, $tipo->value, $destinatario->contaId, $destinatario->pessoaId,
                    $destinatario->chave(), $referencia->tipo, $referencia->id, $chave, $destinatario->destino, $agora, $agora,
                ],
            );

            // Já registrado antes: idempotência por (tipo, referência, destinatário, chave).
            if ($aviso === null) {
                continue;
            }

            if ($emMigracao) {
                Log::info('envio.suprimido', ['motivo' => 'migracao', 'tipo' => $tipo->value, 'aviso_id' => $aviso->id]);

                continue;
            }

            foreach ($tipo->canais() as $canal) {
                $envio = DB::selectOne(
                    'insert into envios (organizacao_id, aviso_id, aviso_criado_em, canal, estado, enfileirado_em, request_id)
                     values (?, ?, ?, ?, ?, ?, ?) returning id',
                    [$organizacaoId, $aviso->id, $aviso->created_at, $canal, EstadoEnvio::Pendente->value, $agora, Context::get('request_id')],
                );

                EnviarAviso::dispatch((int) $envio->id, $organizacaoId)->afterCommit();
            }
        }
    }
}
