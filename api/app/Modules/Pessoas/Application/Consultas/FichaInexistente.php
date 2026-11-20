<?php

namespace App\Modules\Pessoas\Application\Consultas;

use RuntimeException;

final class FichaInexistente extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('pessoa_inexistente');
    }
}
