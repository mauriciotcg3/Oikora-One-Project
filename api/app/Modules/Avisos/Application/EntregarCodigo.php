<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Identidade\Contracts\CodigoParaEnvio;
use App\Modules\Identidade\Contracts\CodigosDeIdentidade;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Entrega um código pelo canal escolhido e recorre ao SMS quando o WhatsApp não resolve (OIK-ADR-0008 §11). */
final class EntregarCodigo
{
    private const TEMPLATE_WHATSAPP = 'authentication';

    public function __construct(
        private readonly CodigosDeIdentidade $codigos,
        private readonly CanalDeEmail $email,
        private readonly CanalDeWhatsApp $whatsApp,
        private readonly CanalDeSms $sms,
        private readonly LimiteDeSms $limiteDeSms,
    ) {}

    /** @throws FalhaTemporariaDeEnvio para a fila tentar de novo */
    public function executar(int $codigoId): void
    {
        // Sem nada a entregar, a tentativa é duplicata ou o código já perdeu a validade.
        $codigo = $this->codigos->paraEnvio($codigoId);
        if ($codigo === null) {
            return;
        }

        if ($codigo->destino === '') {
            $this->falhar($codigoId, $codigo->canal, 'sem_destino');

            return;
        }

        try {
            $idExterno = $this->canalDe($codigo->canal)->enviar($this->mensagem($codigo));
            $this->registrarEntrega($codigoId, $codigo->canal, $idExterno);
        } catch (FalhaDefinitivaDeEnvio $falha) {
            $this->aposFalhaDefinitiva($codigo, $codigoId, $falha->getMessage());
        }
    }

    public function depoisDeEsgotar(int $codigoId, Throwable $erro): void
    {
        $codigo = $this->codigos->paraEnvio($codigoId);
        if ($codigo === null) {
            return;
        }

        // Só o motivo classificado pelo Adapter vai para o banco: outra exceção pode trazer dado pessoal.
        $this->aposFalhaDefinitiva($codigo, $codigoId, $erro instanceof FalhaDeEnvio ? $erro->getMessage() : 'erro_inesperado');
    }

    private function aposFalhaDefinitiva(CodigoParaEnvio $codigo, int $codigoId, string $motivo): void
    {
        if ($codigo->canal !== 'whatsapp' || ! str_starts_with($codigo->destino, '+55')) {
            $this->falhar($codigoId, $codigo->canal, $motivo);

            return;
        }

        if (! $this->limiteDeSms->reservar($codigo->destino)) {
            $this->falhar($codigoId, $codigo->canal, 'limite_sms');

            return;
        }

        try {
            $this->registrarEntrega($codigoId, 'sms', $this->sms->enviar($this->mensagem($codigo)));
        } catch (FalhaDeEnvio $falha) {
            $this->falhar($codigoId, 'sms', $falha->getMessage());
        }
    }

    private function canalDe(string $canal): CanalDeCodigo
    {
        return $canal === 'whatsapp' ? $this->whatsApp : $this->email;
    }

    private function mensagem(CodigoParaEnvio $codigo): MensagemDeCodigo
    {
        return new MensagemDeCodigo(
            destino: $codigo->destino,
            codigo: $codigo->codigo,
            finalidade: $codigo->finalidade,
            template: $codigo->canal === 'whatsapp' ? self::TEMPLATE_WHATSAPP : null,
        );
    }

    private function registrarEntrega(int $codigoId, string $canal, string $idExterno): void
    {
        $this->codigos->registrarEnvio($codigoId, 'enviado', $canal, $idExterno);
        $this->registrarLog($codigoId, $canal, 'enviado');
    }

    private function falhar(int $codigoId, string $canal, string $motivo): void
    {
        $this->codigos->registrarEnvio($codigoId, 'falhou_definitivo', $canal, erro: $motivo);
        $this->registrarLog($codigoId, $canal, 'falhou_definitivo', $motivo);
    }

    private function registrarLog(int $codigoId, string $canal, string $estado, ?string $erro = null): void
    {
        Log::info('avisos.codigo.envio', [
            'module' => 'Avisos',
            'action' => 'enviar_codigo',
            'entity' => 'codigo_verificacao',
            'entity_id' => $codigoId,
            'canal' => $canal,
            'status' => $estado,
            'error' => $erro,
        ]);
    }
}
