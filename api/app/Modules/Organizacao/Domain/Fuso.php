<?php

namespace App\Modules\Organizacao\Domain;

use DateTimeZone;

/** Fuso da organização (IANA), usado na janela diurna dos avisos e nas datas exibidas. */
final class Fuso
{
    public const PADRAO = 'America/Sao_Paulo';

    public static function valido(string $fuso): bool
    {
        return in_array($fuso, DateTimeZone::listIdentifiers(), true);
    }
}
