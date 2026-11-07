<?php

namespace App\Modules\Identidade\Application;

use DateTimeInterface;

interface EventosAutenticacao
{
    public function registrar(string $evento, ?int $contaId, string $identificadorResumo, string $ipResumo, ?string $canal = null): void;

    /** Canal da redefinição de senha mais recente desde `$desde`; null quando não houve. */
    public function canalDaUltimaRedefinicao(int $contaId, DateTimeInterface $desde): ?string;
}
