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
    /** Códigos de erro da Meta que pedem nova tentativa (limite de taxa ou instabilidade). */
    private const TEMPORARIOS = [1, 2, 4, 80007, 130429, 131000, 131048, 131056, 133004];

    /** Códigos de erro com motivo próprio; os demais definitivos viram `meta_<código>`. */
    private const MOTIVOS = [
        131026 => 'numero_sem_whatsapp',
        133010 => 'numero_sem_whatsapp',
        131030 => 'numero_nao_permitido',
        131042 => 'conta_sem_pagamento',
        131031 => 'conta_bloqueada',
        190 => 'credencial_invalida',
        132000 => 'template_rejeitado',
        132001 => 'template_rejeitado',
        132015 => 'template_rejeitado',
        132016 => 'template_rejeitado',
    ];

    public function provedor(): string
    {
        return 'meta';
    }

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

    protected function classificar(Response $resposta): array
    {
        $codigo = (int) $resposta->json('error.code');

        if (in_array($codigo, self::TEMPORARIOS, true) || ($codigo === 0 && $this->statusTemporario($resposta))) {
            return ['meta_indisponivel', true];
        }

        return [self::MOTIVOS[$codigo] ?? 'meta_'.$codigo, false];
    }
}
