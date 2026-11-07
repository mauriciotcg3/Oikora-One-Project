<?php

namespace App\Modules\Identidade\Application;

interface EventosAutenticacao
{
    public function registrar(string $evento, ?int $contaId, string $identificadorResumo, string $ipResumo, ?string $canal = null): void;
}
