<?php

namespace App\Modules\Pessoas\Application\Configuracao;

use App\Modules\Pessoas\Domain\ListaValores;

/**
 * Itens das listas da organização em contexto (RLS). Item desativado sai dos cadastros novos, mas o nome
 * continua resolvido onde já foi usado (CA-30).
 *
 * @phpstan-type ItemLista array{id: int, uuid: string, lista: string, nome: string, ativo: bool, ordem: int}
 */
interface ListasDeValores
{
    /** @return list<ItemLista> ordenados por `ordem` e nome */
    public function listar(ListaValores $lista, bool $soAtivos = false): array;

    /** @return ItemLista|null */
    public function porUuid(ListaValores $lista, string $uuid): ?array;

    public function nomeEmUso(ListaValores $lista, string $nome, ?int $excetoId = null): bool;

    /** @return ItemLista */
    public function criar(int $organizacaoId, ListaValores $lista, string $nome, ?int $ordem): array;

    /**
     * @param  array{nome?: string, ordem?: int, ativo?: bool}  $mudancas
     * @return ItemLista
     */
    public function atualizar(int $id, array $mudancas): array;

    /** Id do item ativo para gravar num cadastro novo; `null` se não existe ou está desativado. */
    public function idAtivo(ListaValores $lista, string $uuid): ?int;

    /**
     * Nomes dos itens já usados, ativos ou não.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function nomes(array $ids): array;
}
