<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\ExigenciaDeDoisFatores;

/** Ainda não existem papéis: ninguém é obrigado. A T-16 troca este Adapter pela consulta ao papel. */
final class SemExigenciaDeDoisFatores implements ExigenciaDeDoisFatores
{
    public function obrigatorio(int $contaId): bool
    {
        return false;
    }
}
