<?php

namespace App\Modules\Identidade\Application;

/** HMAC usado em tudo que não pode ficar em claro: identificador, IP e código. */
final class Resumo
{
    public static function de(string $valor): string
    {
        return hash_hmac('sha256', $valor, (string) config('oikora.chave_resumo'));
    }
}
