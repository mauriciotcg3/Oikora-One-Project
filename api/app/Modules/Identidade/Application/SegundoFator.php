<?php

namespace App\Modules\Identidade\Application;

final readonly class SegundoFator
{
    /** @param list<string> $recuperacao resumos dos códigos de recuperação ainda válidos */
    public function __construct(
        public ?string $canal,
        public ?string $segredoCifrado,
        public array $recuperacao,
    ) {}
}
