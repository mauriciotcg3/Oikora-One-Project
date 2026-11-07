<?php

namespace App\Modules\Organizacao\Domain;

enum ModoMatricula: string
{
    case Manual = 'manual';
    case Automatica = 'automatica';
}
