<?php

namespace App\Modules\Identidade\Domain;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** E-mail ou celular da conta, na forma guardada no banco (OIK-ADR-0003 §1). */
final class Identificador
{
    private const PAIS_PADRAO = 'BR';

    /** E-mail em minúsculas; celular em E.164. O que não for reconhecido volta só sem espaços nas pontas. */
    public static function normalizar(string $valor): string
    {
        $texto = trim($valor);

        return str_contains($texto, '@') ? mb_strtolower($texto) : self::celularE164($texto);
    }

    private static function celularE164(string $texto): string
    {
        $telefones = PhoneNumberUtil::getInstance();

        try {
            $numero = $telefones->parse($texto, self::PAIS_PADRAO);
        } catch (NumberParseException) {
            return $texto;
        }

        return $telefones->isValidNumber($numero) ? $telefones->format($numero, PhoneNumberFormat::E164) : $texto;
    }
}
