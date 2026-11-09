<?php

namespace App\Modules\Organizacao\Contracts;

interface CatalogoIgrejas
{
    /** @return list<int> */
    public function idsDaOrganizacao(int $organizacaoId): array;

    /** @return list<int> */
    public function idsDaRegional(int $organizacaoId, int $regionalId): array;

    public function pertenceAOrganizacao(int $organizacaoId, int $igrejaId): bool;
}
