<?php

namespace App\Modules\Avisos\Infrastructure\Canais\Twilio;

use App\Modules\Avisos\Application\CanalDeSms;
use App\Modules\Avisos\Application\MensagemDeCodigo;
use App\Modules\Avisos\Infrastructure\Canais\CanalHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/** SMS de reserva para códigos, só +55 (OIK-ADR-0008 §11), pela API de Messages do Twilio. */
final class TwilioCodigos extends CanalHttp implements CanalDeSms
{
    /** Códigos de erro do Twilio com motivo próprio; os demais definitivos viram `twilio_<código>`. */
    private const MOTIVOS = [
        20003 => 'credencial_invalida',
        21211 => 'numero_invalido',
        21408 => 'regiao_bloqueada',
        21610 => 'numero_descadastrado',
        21612 => 'numero_nao_alcancavel',
        21614 => 'numero_nao_movel',
    ];

    public function provedor(): string
    {
        return 'twilio';
    }

    public function enviar(MensagemDeCodigo $mensagem): string
    {
        $conta = $this->configuracao('services.twilio.conta_sid');
        $token = $this->configuracao('services.twilio.token');
        $servico = $this->opcional('services.twilio.servico_sid');
        $origem = $servico !== null
            ? ['MessagingServiceSid' => $servico]
            : ['From' => $this->configuracao('services.twilio.remetente')];
        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $conta);

        $resposta = $this->chamar('enviar_codigo', fn (PendingRequest $http): Response => $http
            ->withBasicAuth($conta, $token)
            ->asForm()
            ->post($url, $origem + [
                'To' => $mensagem->destino,
                'Body' => $this->textoDoCodigo($mensagem->codigo, $mensagem->finalidade),
            ]));

        return (string) $resposta->json('sid');
    }

    protected function classificar(Response $resposta): array
    {
        if ($this->statusTemporario($resposta)) {
            return ['twilio_indisponivel', true];
        }

        $codigo = (int) $resposta->json('code');

        return [self::MOTIVOS[$codigo] ?? 'twilio_'.$codigo, false];
    }
}
