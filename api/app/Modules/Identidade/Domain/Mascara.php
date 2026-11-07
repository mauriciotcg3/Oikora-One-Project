<?php

namespace App\Modules\Identidade\Domain;

/** Destino do código de 2FA como a pessoa pode vê-lo, sem revelar o contato inteiro. */
final class Mascara
{
    public static function email(string $email): string
    {
        [$usuario, $dominio] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($usuario, 0, 1).'•••@'.$dominio;
    }

    /** Recebe o celular em E.164, como +5511987654321. */
    public static function celular(string $celular): string
    {
        return '('.substr($celular, 3, 2).') •••••-'.substr($celular, -4);
    }
}
