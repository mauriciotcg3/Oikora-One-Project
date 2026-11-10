<?php

namespace App\Modules\Organizacao\Contracts;

interface ConsultaOrganizacoes
{
    /** @return list<int> */
    public function idsDisponiveis(): array;

    /** Organização em migração: o módulo Avisos não cria envios (OIK-ADR-0009 §10). */
    public function emMigracao(int $organizacaoId): bool;
}
