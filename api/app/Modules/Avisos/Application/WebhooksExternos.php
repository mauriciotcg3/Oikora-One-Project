<?php

namespace App\Modules\Avisos\Application;

interface WebhooksExternos
{
    public function autenticar(string $provedor, RequisicaoWebhook $requisicao): bool;

    /** @return list<EventoWebhook> */
    public function normalizar(string $provedor, RequisicaoWebhook $requisicao): array;
}
