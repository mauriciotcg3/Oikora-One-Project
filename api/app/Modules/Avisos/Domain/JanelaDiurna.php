<?php

namespace App\Modules\Avisos\Domain;

use DateTimeImmutable;
use DateTimeZone;

/** Envio externo de tipos que podem esperar só entre 8h e 21h no fuso da organização (OIK-ADR-0008 §14). */
final class JanelaDiurna
{
    public const INICIO = 8;

    public const FIM = 21;

    /**
     * Null quando `$agora` está na janela; senão, as 8h seguintes no fuso da organização, devolvidas no
     * fuso de `$agora` (o banco grava data sem deslocamento, no fuso do app).
     */
    public static function proximoEnvio(DateTimeImmutable $agora, string $fuso): ?DateTimeImmutable
    {
        $local = $agora->setTimezone(new DateTimeZone($fuso));
        $hora = (int) $local->format('G');

        if ($hora >= self::INICIO && $hora < self::FIM) {
            return null;
        }

        return ($hora < self::INICIO ? $local : $local->modify('+1 day'))
            ->setTime(self::INICIO, 0)
            ->setTimezone($agora->getTimezone());
    }
}
