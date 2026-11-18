<?php

namespace App\Modules\Identidade\Contracts;

/**
 * Leitura dos acessos da organização em contexto (RLS). Identidade é dona de `acessos`; os outros
 * módulos perguntam por aqui (MOD-002, DB-001).
 */
interface AcessosDaOrganizacao
{
    /** Acesso ativo da conta na organização do contexto, ou null. */
    public function ativoDaConta(int $contaId): ?int;

    /** @return array{id: int, ativo: bool}|null */
    public function porUuid(string $uuid): ?array;

    /**
     * @param  list<int>  $ids
     * @return list<int> só os que estão ativos
     */
    public function ativos(array $ids): array;

    /**
     * Identificador público de cada acesso da organização em contexto, para respostas (ex.: ator da
     * auditoria). Acesso revogado continua com o mesmo uuid.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function uuids(array $ids): array;
}
