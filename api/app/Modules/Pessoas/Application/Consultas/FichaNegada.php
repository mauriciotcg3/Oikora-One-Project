<?php

namespace App\Modules\Pessoas\Application\Consultas;

use RuntimeException;

/** A pessoa existe na organização, mas está fora do alcance de quem consulta (RN-09). */
final class FichaNegada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('authz.denied');
    }
}
