<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\TipoAviso;

/** O que o Adapter recebe: tipo (template), destino e o link; o texto vem do template do provedor. */
final readonly class MensagemDeAviso
{
    public function __construct(
        public TipoAviso $tipo,
        public string $canal,
        public string $destino,
        public ?string $link,
        public string $chaveDeIdempotencia,
    ) {}
}
