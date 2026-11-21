<?php

namespace App\Modules\Avisos\Application;

final readonly class EventoWebhook
{
    public function __construct(
        public string $provedor,
        public string $tipo,
        public string $idExterno,
        public ?string $erroCodigo = null,
    ) {}
}
