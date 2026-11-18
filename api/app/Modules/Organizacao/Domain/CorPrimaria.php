<?php

namespace App\Modules\Organizacao\Domain;

/**
 * Cor primária da marca da organização (OIK-ADR-0006 §7). No tema, ela é fundo de botão com texto branco
 * e cor de link sobre fundo branco: nos dois casos o par é cor × branco, que precisa de contraste AA
 * (WCAG 2.x, 4,5:1 para texto normal).
 */
final class CorPrimaria
{
    public const TEXTO_DO_TEMA = '#FFFFFF';

    public const CONTRASTE_MINIMO = 4.5;

    public static function formatoValido(string $hex): bool
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) === 1;
    }

    public static function temContrasteAA(string $hex): bool
    {
        return self::formatoValido($hex) && self::contraste($hex, self::TEXTO_DO_TEMA) >= self::CONTRASTE_MINIMO;
    }

    public static function contraste(string $a, string $b): float
    {
        $claras = [self::luminancia($a), self::luminancia($b)];
        rsort($claras);

        return ($claras[0] + 0.05) / ($claras[1] + 0.05);
    }

    private static function luminancia(string $hex): float
    {
        $canais = array_map(
            static function (string $par): float {
                $c = hexdec($par) / 255;

                return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($hex, 1), 2),
        );

        return 0.2126 * $canais[0] + 0.7152 * $canais[1] + 0.0722 * $canais[2];
    }
}
