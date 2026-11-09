<?php

namespace App\Shared\Tenancy\Contracts;

interface OrganizacaoDisponivel
{
    public function verificar(int $organizacaoId): bool;
}
