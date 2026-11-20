<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Autorizacao\Contracts\PapeisDaConta;
use App\Modules\Identidade\Application\ExigenciaDeDoisFatores;

/** RN-16: o 2FA é obrigatório para quem tem o papel Administrador em alguma organização. */
final readonly class DoisFatoresPeloPapel implements ExigenciaDeDoisFatores
{
    public function __construct(private PapeisDaConta $papeis) {}

    public function obrigatorio(int $contaId): bool
    {
        return $this->papeis->administrador($contaId);
    }
}
