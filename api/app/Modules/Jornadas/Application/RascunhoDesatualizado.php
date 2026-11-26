<?php

namespace App\Modules\Jornadas\Application;

use RuntimeException;

final class RascunhoDesatualizado extends RuntimeException
{
    /** @param array<string, mixed>|null $atual */
    public function __construct(public readonly ?array $atual)
    {
        parent::__construct('O rascunho foi atualizado em outra sessão.');
    }
}
