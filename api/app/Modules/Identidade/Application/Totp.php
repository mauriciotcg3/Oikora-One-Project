<?php

namespace App\Modules\Identidade\Application;

interface Totp
{
    public function novoSegredo(): string;

    public function urlDeProvisionamento(string $rotulo, string $segredo): string;

    /** Confere o código do aplicativo. O mesmo código não vale duas vezes para a conta. */
    public function conferir(int $contaId, string $segredo, string $codigo): bool;
}
