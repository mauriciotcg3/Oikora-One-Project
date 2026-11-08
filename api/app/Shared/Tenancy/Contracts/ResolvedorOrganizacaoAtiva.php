<?php

namespace App\Shared\Tenancy\Contracts;

use Illuminate\Http\Request;

/** Porta implementada por Identidade na T-16 para revalidar a organização guardada no servidor. */
interface ResolvedorOrganizacaoAtiva
{
    public function resolver(Request $request): int;
}
