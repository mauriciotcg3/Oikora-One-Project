<?php

namespace App\Modules\Organizacao\Contracts;

interface CatalogoIgrejas
{
    /** @return list<int> */
    public function idsDaOrganizacao(int $organizacaoId): array;

    /** @return list<int> */
    public function idsDaRegional(int $organizacaoId, int $regionalId): array;

    public function pertenceAOrganizacao(int $organizacaoId, int $igrejaId): bool;

    /** Id da igreja da organização em contexto (RLS), pelo identificador público. */
    public function igrejaPorUuid(string $uuid): ?int;

    /** Id da regional da organização em contexto (RLS), pelo identificador público. */
    public function regionalPorUuid(string $uuid): ?int;

    /**
     * Identificador público e nome, para respostas que mostram a igreja.
     *
     * @param  list<int>  $ids
     * @return array<int, array{uuid: string, nome: string}>
     */
    public function resumo(array $ids): array;
}
