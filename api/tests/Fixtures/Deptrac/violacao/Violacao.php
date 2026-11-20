<?php

namespace App\Modules\Exemplo\Domain;

use Illuminate\Support\Str;

/** Fixture do GateTest: um Domain que importa o framework precisa reprovar o Deptrac. Nunca é carregado. */
final class Violacao
{
    public function slug(string $texto): string
    {
        return Str::slug($texto);
    }
}
