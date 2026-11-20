<?php

namespace App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp;

use App\Modules\Avisos\Application\CanalDeAviso;
use App\Modules\Avisos\Application\FalhaDefinitivaDeEnvio;
use App\Modules\Avisos\Application\MensagemDeAviso;
use App\Modules\Avisos\Application\NumerosWhatsApp;
use App\Modules\Avisos\Infrastructure\Canais\CanalHttp;
use App\Modules\Avisos\Infrastructure\TextosDeAviso;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Aviso pelo número da organização na Cloud API da Meta (OIK-ADR-0008 §10, §12), com template de categoria
 * `utility` por tipo (`convite_painel`, `servico_transferencia_solicitada`...). O corpo leva só o nome da
 * organização; o botão abre o link universal. Sem número ativo, falha definitiva: nunca usa o da plataforma.
 */
final class MetaWhatsAppAvisos extends CanalHttp implements CanalDeAviso
{
    use ErrosDaMeta;

    public function __construct(private readonly NumerosWhatsApp $numeros) {}

    public function enviarAviso(MensagemDeAviso $mensagem): string
    {
        $numero = $this->numeros->daOrganizacao($mensagem->organizacaoId)
            ?? throw new FalhaDefinitivaDeEnvio('sem_numero_whatsapp');
        $versao = $this->configuracao('services.meta_whatsapp.versao');
        $idioma = $this->configuracao('services.meta_whatsapp.idioma');

        $componentes = [['type' => 'body', 'parameters' => [
            ['type' => 'text', 'text' => TextosDeAviso::nomeSeguro($mensagem->nomeOrganizacao)],
        ]]];
        if ($mensagem->link !== null) {
            $componentes[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [
                ['type' => 'text', 'text' => ltrim($mensagem->link, '/')],
            ]];
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $versao, $numero->phoneNumberId);
        $resposta = $this->chamar('enviar_aviso', fn (PendingRequest $http): Response => $http
            ->withToken($numero->token)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($mensagem->destino, '+'),
                'type' => 'template',
                'template' => [
                    'name' => str_replace('.', '_', $mensagem->tipo->value),
                    'language' => ['code' => $idioma],
                    'components' => $componentes,
                ],
            ]));

        return (string) $resposta->json('messages.0.id');
    }
}
