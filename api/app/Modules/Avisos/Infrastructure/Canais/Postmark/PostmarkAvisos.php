<?php

namespace App\Modules\Avisos\Infrastructure\Canais\Postmark;

use App\Modules\Avisos\Application\CanalDeAviso;
use App\Modules\Avisos\Application\MensagemDeAviso;
use App\Modules\Avisos\Infrastructure\Canais\CanalHttp;
use App\Modules\Avisos\Infrastructure\TextosDeAviso;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Aviso por e-mail pela API do Postmark (OIK-ADR-0008 §9): remetente "Nome via Oikora" no domínio da
 * plataforma, responder-para no contato da organização, template Blade sem dado da ficha e, em tipo não
 * obrigatório, `List-Unsubscribe` com descadastro em um clique (RFC 8058).
 */
final class PostmarkAvisos extends CanalHttp implements CanalDeAviso
{
    use ErrosDoPostmark;

    private const URL = 'https://api.postmarkapp.com/email';

    public function enviarAviso(MensagemDeAviso $mensagem): string
    {
        $token = $this->configuracao('services.postmark.token');
        $endereco = $this->configuracao('services.postmark.remetente_avisos');
        $dados = TextosDeAviso::dados($mensagem);

        $corpo = [
            'From' => sprintf('"%s via Oikora" <%s>', TextosDeAviso::nomeSeguro($mensagem->nomeOrganizacao), $endereco),
            'To' => $mensagem->destino,
            'Subject' => $dados['assunto'],
            'HtmlBody' => view('emails.avisos.aviso', $dados)->render(),
            'TextBody' => view('emails.avisos.aviso-texto', $dados)->render(),
            'MessageStream' => $this->opcional('services.postmark.stream') ?? 'outbound',
            'Tag' => $mensagem->tipo->value,
            'TrackOpens' => false,
            'Metadata' => ['envio' => $mensagem->chaveDeIdempotencia],
        ];

        if ($mensagem->responderPara !== null) {
            $corpo['ReplyTo'] = $mensagem->responderPara;
        }

        if ($mensagem->linkDescadastro !== null) {
            $corpo['Headers'] = [
                ['Name' => 'List-Unsubscribe', 'Value' => '<'.$mensagem->linkDescadastro.'>'],
                ['Name' => 'List-Unsubscribe-Post', 'Value' => 'List-Unsubscribe=One-Click'],
            ];
        }

        $resposta = $this->chamar('enviar_aviso', fn (PendingRequest $http): Response => $http
            ->withHeaders(['X-Postmark-Server-Token' => $token])
            ->post(self::URL, $corpo));

        return (string) $resposta->json('MessageID');
    }
}
