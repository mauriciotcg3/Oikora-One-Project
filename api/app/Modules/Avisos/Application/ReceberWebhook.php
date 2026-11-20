<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Application\Jobs\ProcessarWebhook;
use Illuminate\Support\Facades\Log;

final readonly class ReceberWebhook
{
    public function __construct(private WebhooksExternos $webhooks) {}

    public function executar(string $provedor, RequisicaoWebhook $requisicao): bool
    {
        if (! $this->webhooks->autenticar($provedor, $requisicao)) {
            Log::warning('webhook.assinatura_invalida', ['provedor' => $provedor, 'ip' => $requisicao->ip]);

            return false;
        }

        foreach ($this->webhooks->normalizar($provedor, $requisicao) as $evento) {
            ProcessarWebhook::dispatch(
                $evento->provedor,
                $evento->tipo,
                $evento->idExterno,
                $evento->erroCodigo,
            );
        }

        return true;
    }
}
