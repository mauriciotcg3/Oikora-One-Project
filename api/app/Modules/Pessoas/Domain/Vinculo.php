<?php

namespace App\Modules\Pessoas\Domain;

/** Vínculos da pessoa com a igreja (RN-03). Só `membro` tem matrícula. */
enum Vinculo: string
{
    case Visitante = 'visitante';
    case Frequentador = 'frequentador';
    case Membro = 'membro';
}
