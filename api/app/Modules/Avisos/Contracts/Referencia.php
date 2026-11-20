<?php

namespace App\Modules\Avisos\Contracts;

/** O fato que originou o aviso (ex.: `convite`, uuid do convite). Entra na chave de idempotência. */
final readonly class Referencia
{
    public function __construct(
        public string $tipo,
        public string $id,
    ) {}
}
