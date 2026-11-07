<?php

namespace App\Modules\Avisos\Application;

final readonly class MensagemDeCodigo
{
    public function __construct(
        public string $destino,
        public string $codigo,
        public string $finalidade,
        public ?string $template = null,
    ) {}
}
