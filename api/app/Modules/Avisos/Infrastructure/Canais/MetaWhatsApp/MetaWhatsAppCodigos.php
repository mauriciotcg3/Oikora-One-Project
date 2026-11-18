<?php

namespace App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp;

use App\Modules\Avisos\Application\CanalDeWhatsApp;
use App\Modules\Avisos\Application\MensagemDeCodigo;
use App\Modules\Avisos\Infrastructure\Canais\CanalHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Código pelo número da plataforma na Cloud API da Meta, com template de categoria `authentication`
 * (OIK-ADR-0008 §10): o código vai no corpo e no botão de copiar.
 */
final class MetaWhatsAppCodigos extends CanalHttp implements CanalDeWhatsApp
{
    use ErrosDaMeta;

    public function enviar(MensagemDeCodigo $mensagem): string
    {
        $token = $this->configuracao('services.meta_whatsapp.token');
        $numeroId = $this->configuracao('services.meta_whatsapp.numero_id');
        $versao = $this->configuracao('services.meta_whatsapp.versao');
        $idioma = $this->configuracao('services.meta_whatsapp.idioma');
        $template = $this->opcional('services.meta_whatsapp.template_codigo') ?? $mensagem->template ?? 'authentication';

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $versao, $numeroId);
        $parametro = [['type' => 'text', 'text' => $mensagem->codigo]];

        $resposta = $this->chamar('enviar_codigo', fn (PendingRequest $http): Response => $http
            ->withToken($token)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($mensagem->destino, '+'),
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $idioma],
                    'components' => [
                        ['type' => 'body', 'parameters' => $parametro],
                        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => $parametro],
                    ],
                ],
            ]));

        return (string) $resposta->json('messages.0.id');
    }
}
