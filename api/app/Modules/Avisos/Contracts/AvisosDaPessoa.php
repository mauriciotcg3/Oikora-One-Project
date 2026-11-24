<?php

namespace App\Modules\Avisos\Contracts;

/**
 * Parte do removedor de Pessoas que cabe a Avisos (OIK-ADR-0005 §8; OIK-ADR-0008 §17): avisos e envios
 * da Pessoa sem conta. `anonimizar` tira destino, hash e link; `apagar` remove avisos e envios.
 */
interface AvisosDaPessoa
{
    public function remover(int $pessoaId, string $modo): void;
}
