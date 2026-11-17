<?php

namespace App\Modules\Avisos\Infrastructure\Canais\Postmark;

use App\Modules\Avisos\Application\CanalDeEmail;
use App\Modules\Avisos\Application\MensagemDeCodigo;
use App\Modules\Avisos\Infrastructure\Canais\CanalHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/** E-mail de código pela API do Postmark (`POST /email`), stream transacional. */
final class PostmarkCodigos extends CanalHttp implements CanalDeEmail
{
    private const URL = 'https://api.postmarkapp.com/email';

    /** ErrorCode do Postmark que não se resolvem com nova tentativa. */
    private const DEFINITIVOS = [
        10 => 'credencial_invalida',
        300 => 'email_invalido',
        406 => 'destinatario_inativo',
        412 => 'conta_restrita',
    ];

    public function provedor(): string
    {
        return 'postmark';
    }

    public function enviar(MensagemDeCodigo $mensagem): string
    {
        $token = $this->configuracao('services.postmark.token');
        $remetente = $this->configuracao('services.postmark.remetente');

        $resposta = $this->chamar('enviar_codigo', fn (PendingRequest $http): Response => $http
            ->withHeaders(['X-Postmark-Server-Token' => $token])
            ->post(self::URL, [
                'From' => $remetente,
                'To' => $mensagem->destino,
                'Subject' => 'Seu código Oikora',
                'TextBody' => $this->textoDoCodigo($mensagem->codigo, $mensagem->finalidade),
                'MessageStream' => $this->opcional('services.postmark.stream') ?? 'outbound',
                'Tag' => 'codigo-'.$mensagem->finalidade,
                'TrackOpens' => false,
            ]));

        return (string) $resposta->json('MessageID');
    }

    protected function classificar(Response $resposta): array
    {
        if ($this->statusTemporario($resposta)) {
            return ['postmark_indisponivel', true];
        }

        $codigo = (int) $resposta->json('ErrorCode');

        return [self::DEFINITIVOS[$codigo] ?? 'postmark_'.$codigo, false];
    }
}
