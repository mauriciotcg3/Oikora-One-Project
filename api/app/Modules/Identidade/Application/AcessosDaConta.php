<?php

namespace App\Modules\Identidade\Application;

/** Organizações em que a conta tem acesso ativo, lidas sem contexto pela via estreita (OIK-ADR-0003 §12). */
interface AcessosDaConta
{
    /** @return list<OrganizacaoDaConta> */
    public function organizacoes(int $contaId): array;
}
