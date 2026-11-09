<?php

namespace App\Modules\Organizacao\Contracts;

interface ConsultaOrganizacoes
{
    /** @return list<int> */
    public function idsDisponiveis(): array;
}
