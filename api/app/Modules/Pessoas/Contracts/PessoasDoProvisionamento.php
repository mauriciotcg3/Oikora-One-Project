<?php

namespace App\Modules\Pessoas\Contracts;

/**
 * Ficha do primeiro administrador, criada pelo provisionamento da organização (plan.md §3.3, §4) como
 * rotina de sistema, com a organização no contexto. Sem ator: o evento de entrada fica sem autor.
 */
interface PessoasDoProvisionamento
{
    /**
     * @return array{id: int, uuid: string}
     *
     * @throws \InvalidArgumentException quando o celular não é válido
     */
    public function criarAdministrador(int $organizacaoId, int $igrejaId, string $nome, ?string $email, ?string $celular): array;
}
