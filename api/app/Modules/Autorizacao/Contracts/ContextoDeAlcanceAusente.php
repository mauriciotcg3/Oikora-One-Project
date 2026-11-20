<?php

namespace App\Modules\Autorizacao\Contracts;

use RuntimeException;

/** Consulta com escopo de igreja sem alcance definido: erro, nunca lista vazia (OIK-ADR-0004 §5). */
final class ContextoDeAlcanceAusente extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('authz.context_missing');
    }
}
