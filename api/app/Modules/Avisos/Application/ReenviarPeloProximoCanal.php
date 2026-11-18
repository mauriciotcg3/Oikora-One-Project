<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\TipoAviso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reenvio do aviso de serviço pelo próximo canal (OIK-ADR-0008 §12): depois de falha definitiva, ou quando
 * o WhatsApp não confirma entrega em 30 minutos. Roda no contexto da organização; repetir não duplica.
 */
final readonly class ReenviarPeloProximoCanal
{
    public const ESPERA_ENTREGA_WHATSAPP_MINUTOS = 30;

    public function __construct(private CaixaDeSaida $caixa) {}

    /** @return string|null o canal do novo envio */
    public function reenviar(int $avisoId, string $motivo): ?string
    {
        $aviso = DB::selectOne('select id, organizacao_id, tipo, conta_id, pessoa_id, created_at from avisos where id = ?', [$avisoId]);
        if ($aviso === null) {
            return null;
        }

        $tipo = TipoAviso::from($aviso->tipo);
        if (! $tipo->reenviaPeloProximoCanal()) {
            return null;
        }

        $organizacaoId = (int) $aviso->organizacao_id;
        $contaId = $aviso->conta_id === null ? null : (int) $aviso->conta_id;
        $pessoaId = $aviso->pessoa_id === null ? null : (int) $aviso->pessoa_id;
        /** @var list<string> $tentados */
        $tentados = DB::table('envios')->where('aviso_id', $avisoId)->pluck('canal')->all();

        $canal = $this->caixa->proximoCanal($organizacaoId, $tipo, $contaId, $pessoaId, $tentados);
        if ($canal === null) {
            Log::warning('envio.sem_proximo_canal', ['tipo' => $tipo->value, 'motivo' => $motivo]);

            return null;
        }

        $this->caixa->criarEnvio($organizacaoId, $avisoId, (string) $aviso->created_at, $tipo, $canal);
        Log::info('envio.reenviado_pelo_proximo_canal', ['tipo' => $tipo->value, 'canal' => $canal, 'motivo' => $motivo]);

        return $canal;
    }
}
