<?php

namespace App\Modules\Pessoas\Domain;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** Contato na forma guardada em `pessoa_contatos`: celular em E.164 e e-mail minúsculo (P §14). */
final class Contato
{
    private const PAIS_PADRAO = 'BR';

    /** @return string|null E.164, ou null quando o número não é válido */
    public static function celular(string $valor): ?string
    {
        $telefones = PhoneNumberUtil::getInstance();

        try {
            $numero = $telefones->parse(trim($valor), self::PAIS_PADRAO);
        } catch (NumberParseException) {
            return null;
        }

        return $telefones->isValidNumber($numero) ? $telefones->format($numero, PhoneNumberFormat::E164) : null;
    }

    public static function email(string $valor): string
    {
        return mb_strtolower(trim($valor));
    }

    /** Nome como é gravado: sem espaços nas pontas nem repetidos. */
    public static function nome(string $valor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $valor));
    }
}
