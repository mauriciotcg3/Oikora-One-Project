<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\EstadoEnvio;
use App\Modules\Avisos\Domain\TipoAviso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

/**
 * Uma tentativa de envio (OIK-ADR-0008 §3, §4). Roda dentro da transação do job (contexto RLS): trava o
 * envio com `FOR UPDATE SKIP LOCKED`, confere o estado e só então chama o provedor. Devolve a espera em
 * segundos para a próxima tentativa, ou null quando não há o que tentar de novo.
 */
final readonly class EntregarAviso
{
    /** Espera depois da 1ª, 2ª, 3ª e 4ª falha temporária; a 5ª vira definitiva. */
    public const ESPERAS = [60, 300, 1800, 7200];

    public const MAXIMO_DE_TENTATIVAS = 5;

    public function __construct(
        private CanaisDeAviso $canais,
        private DestinosDeAviso $destinos,
    ) {}

    public function executar(int $envioId): ?int
    {
        $envio = DB::selectOne(
            'select e.id, e.canal, e.estado, e.tentativas, a.tipo, a.conta_id, a.pessoa_id, a.destino, a.uuid as aviso_uuid
               from envios e join avisos a on a.id = e.aviso_id
              where e.id = ?
              for update of e skip locked',
            [$envioId],
        );

        // Outro worker está com ele, ou já não existe: nada a fazer.
        if (! $envio instanceof stdClass) {
            return null;
        }

        if (! EstadoEnvio::from($envio->estado)->podeEnviar()) {
            return null;
        }

        $canal = (string) $envio->canal;
        $endereco = $this->destinos->endereco(
            $envio->conta_id === null ? null : (int) $envio->conta_id,
            $envio->pessoa_id === null ? null : (int) $envio->pessoa_id,
            $canal,
        );

        if ($endereco === null) {
            $this->falharDefinitivo($envioId, 'sem_destino');

            return null;
        }

        $tentativa = (int) $envio->tentativas + 1;
        $adapter = $this->canais->doCanal($canal);
        $this->atualizar($envioId, [
            'estado' => EstadoEnvio::Enviando->value,
            'tentativas' => $tentativa,
            'enviando_em' => now(),
            'provedor' => $adapter->provedor(),
            'destino_mascarado' => $this->mascarar($endereco),
            'destino_hash' => hash_hmac('sha256', strtolower($endereco), (string) config('oikora.chave_resumo')),
        ]);

        try {
            $idExterno = $adapter->enviarAviso(new MensagemDeAviso(
                tipo: TipoAviso::from($envio->tipo),
                canal: $canal,
                destino: $endereco,
                link: $envio->destino === null ? null : (string) $envio->destino,
                chaveDeIdempotencia: $envio->aviso_uuid.':'.$canal,
            ));
        } catch (FalhaDefinitivaDeEnvio $falha) {
            $this->falharDefinitivo($envioId, $falha->getMessage());

            return null;
        } catch (Throwable $falha) {
            // Temporária, ou erro não classificado: nunca grava a mensagem crua, que pode ter dado pessoal.
            return $this->falharTemporario($envioId, $tentativa, $falha instanceof FalhaTemporariaDeEnvio ? $falha->getMessage() : 'erro_inesperado');
        }

        $this->atualizar($envioId, [
            'estado' => EstadoEnvio::Enviado->value,
            'id_externo' => $idExterno,
            'enviado_em' => now(),
            'erro_codigo' => null,
        ]);
        Log::info('envio.enviado', ['canal' => $canal, 'tentativa' => $tentativa]);

        return null;
    }

    private function falharTemporario(int $envioId, int $tentativa, string $motivo): ?int
    {
        if ($tentativa >= self::MAXIMO_DE_TENTATIVAS) {
            $this->falharDefinitivo($envioId, 'tentativas_esgotadas');

            return null;
        }

        $this->atualizar($envioId, ['estado' => EstadoEnvio::FalhouTemporario->value, 'erro_codigo' => $motivo]);
        Log::warning('envio.falhou_temporario', ['motivo' => $motivo, 'tentativa' => $tentativa]);

        return self::ESPERAS[$tentativa - 1];
    }

    private function falharDefinitivo(int $envioId, string $motivo): void
    {
        $this->atualizar($envioId, [
            'estado' => EstadoEnvio::FalhouDefinitivo->value,
            'erro_codigo' => $motivo,
            'falhou_em' => now(),
        ]);
        Log::warning('envio.falhou_definitivo', ['motivo' => $motivo]);
    }

    /** @param  array<string, mixed>  $campos */
    private function atualizar(int $envioId, array $campos): void
    {
        DB::table('envios')->where('id', $envioId)->update([...$campos, 'updated_at' => now()]);
    }

    private function mascarar(string $endereco): string
    {
        if (str_contains($endereco, '@')) {
            [$usuario, $dominio] = explode('@', $endereco, 2);

            return mb_substr($usuario, 0, 1).'***@'.$dominio;
        }

        return '***'.substr(preg_replace('/\D+/', '', $endereco) ?? '', -4);
    }
}
