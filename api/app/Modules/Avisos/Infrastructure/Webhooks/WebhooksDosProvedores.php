<?php

namespace App\Modules\Avisos\Infrastructure\Webhooks;

use App\Modules\Avisos\Application\EventoWebhook;
use App\Modules\Avisos\Application\RequisicaoWebhook;
use App\Modules\Avisos\Application\WebhooksExternos;
use Illuminate\Support\Str;

final class WebhooksDosProvedores implements WebhooksExternos
{
    public function autenticar(string $provedor, RequisicaoWebhook $requisicao): bool
    {
        return match ($provedor) {
            'meta' => $this->autenticarMeta($requisicao),
            'postmark' => $this->autenticarPostmark($requisicao),
            'twilio' => $this->autenticarTwilio($requisicao),
            default => false,
        };
    }

    public function normalizar(string $provedor, RequisicaoWebhook $requisicao): array
    {
        return match ($provedor) {
            'meta' => $this->eventosMeta($requisicao),
            'postmark' => $this->eventosPostmark($requisicao),
            'twilio' => $this->eventosTwilio($requisicao),
            default => [],
        };
    }

    private function autenticarMeta(RequisicaoWebhook $requisicao): bool
    {
        $segredo = (string) config('oikora.webhooks.meta.app_secret');
        if ($segredo === '') {
            return false;
        }

        $esperada = 'sha256='.hash_hmac('sha256', $requisicao->corpo, $segredo);

        return hash_equals($esperada, (string) ($requisicao->cabecalhos['x-hub-signature-256'] ?? ''));
    }

    private function autenticarPostmark(RequisicaoWebhook $requisicao): bool
    {
        $usuario = (string) config('oikora.webhooks.postmark.usuario');
        $senha = (string) config('oikora.webhooks.postmark.senha');
        if ($usuario === '' || $senha === '') {
            return false;
        }

        $esperada = 'Basic '.base64_encode($usuario.':'.$senha);

        return hash_equals($esperada, (string) ($requisicao->cabecalhos['authorization'] ?? ''));
    }

    private function autenticarTwilio(RequisicaoWebhook $requisicao): bool
    {
        $token = (string) config('oikora.webhooks.twilio.auth_token');
        if ($token === '') {
            return false;
        }

        $parametros = $requisicao->parametros;
        ksort($parametros, SORT_STRING);
        $base = $requisicao->url;
        foreach ($parametros as $nome => $valor) {
            if (is_scalar($valor)) {
                $base .= $nome.(string) $valor;
            }
        }

        $esperada = base64_encode(hash_hmac('sha1', $base, $token, true));

        return hash_equals($esperada, (string) ($requisicao->cabecalhos['x-twilio-signature'] ?? ''));
    }

    /** @return list<EventoWebhook> */
    private function eventosMeta(RequisicaoWebhook $requisicao): array
    {
        $dados = json_decode($requisicao->corpo, true);
        if (! is_array($dados)) {
            return [];
        }

        $eventos = [];
        foreach ($dados['entry'] ?? [] as $entrada) {
            foreach ($entrada['changes'] ?? [] as $alteracao) {
                foreach ($alteracao['value']['statuses'] ?? [] as $status) {
                    $tipo = $this->tipoDeEstado((string) ($status['status'] ?? ''));
                    $id = (string) ($status['id'] ?? '');
                    if ($tipo !== null && $id !== '') {
                        $codigo = $status['errors'][0]['code'] ?? null;
                        $eventos[] = new EventoWebhook('meta', $tipo, $id, $codigo === null ? null : (string) $codigo);
                    }
                }
            }
        }

        return $eventos;
    }

    /** @return list<EventoWebhook> */
    private function eventosPostmark(RequisicaoWebhook $requisicao): array
    {
        $dados = json_decode($requisicao->corpo, true);
        if (! is_array($dados) || ! is_string($dados['MessageID'] ?? null)) {
            return [];
        }

        $tipo = match ($dados['RecordType'] ?? null) {
            'Delivery' => 'entregue',
            'Bounce' => 'falhou_definitivo',
            'SpamComplaint' => 'reclamacao_spam',
            default => null,
        };
        if ($tipo === null) {
            return [];
        }

        $erro = $tipo === 'falhou_definitivo'
            ? Str::snake((string) ($dados['Type'] ?? 'bounce'))
            : null;

        return [new EventoWebhook('postmark', $tipo, $dados['MessageID'], $erro)];
    }

    /** @return list<EventoWebhook> */
    private function eventosTwilio(RequisicaoWebhook $requisicao): array
    {
        $tipo = $this->tipoDeEstado((string) ($requisicao->parametros['MessageStatus'] ?? ''));
        $id = (string) ($requisicao->parametros['MessageSid'] ?? '');
        if ($tipo === null || $id === '') {
            return [];
        }

        $codigo = $requisicao->parametros['ErrorCode'] ?? null;

        return [new EventoWebhook('twilio', $tipo, $id, $codigo === null ? null : (string) $codigo)];
    }

    private function tipoDeEstado(string $estado): ?string
    {
        return match ($estado) {
            'sent' => 'enviado',
            'delivered' => 'entregue',
            'read' => 'lido',
            'failed', 'undelivered' => 'falhou_definitivo',
            default => null,
        };
    }
}
