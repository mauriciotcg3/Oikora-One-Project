<?php

namespace App\Modules\Pessoas\Application\Lixeira;

/**
 * Removedor de Pessoas (OIK-ADR-0005 §8; RN-19; plan.md §3.5). Modo lido de `lgpd.modo_exclusao`, sem
 * padrão: em `production` sem modo recusa com `lgpd.erasure.blocked_no_policy`. Roda na organização em
 * contexto, como rotina de sistema.
 */
interface RemovedorDePessoas
{
    /**
     * @return string o modo aplicado (`anonimizar` ou `apagar`)
     *
     * @throws ExclusaoBloqueada
     */
    public function remover(int $pessoaId, string $origem = 'purga'): string;

    /** @throws ExclusaoBloqueada */
    public function modo(): string;
}
