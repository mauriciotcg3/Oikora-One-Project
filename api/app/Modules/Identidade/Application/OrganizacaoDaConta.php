<?php

namespace App\Modules\Identidade\Application;

final readonly class OrganizacaoDaConta
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $nome,
    ) {}
}
