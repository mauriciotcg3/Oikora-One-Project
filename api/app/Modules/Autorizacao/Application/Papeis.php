<?php

namespace App\Modules\Autorizacao\Application;

/**
 * Papéis e atribuições da organização em contexto (RLS).
 *
 * @phpstan-type PapelLido array{id: int, uuid: string, nome: string, sistema: string|null, permissoes: list<string>}
 * @phpstan-type AtribuicaoLida array{id: int, uuid: string, acesso_id: int, papel_id: int, papel_uuid: string, sistema: string|null, escopo_tipo: string, escopo_id: int|null}
 */
interface Papeis
{
    /** @return list<PapelLido> */
    public function listar(): array;

    /** @return PapelLido|null */
    public function porUuid(string $uuid): ?array;

    public function nomeEmUso(string $nome, ?int $excetoId = null): bool;

    /**
     * @param  list<string>  $permissoes
     * @return PapelLido
     */
    public function criar(int $organizacaoId, string $nome, array $permissoes): array;

    /** @param  list<string>  $permissoes */
    public function atualizar(int $organizacaoId, int $papelId, string $nome, array $permissoes): void;

    /** @return AtribuicaoLida|null */
    public function atribuicaoDoAcesso(int $acessoId, string $uuid): ?array;

    public function atribuicaoExiste(int $acessoId, int $papelId, string $escopoTipo, ?int $escopoId): bool;

    /** @return array{id: int, uuid: string} */
    public function atribuir(int $organizacaoId, int $acessoId, int $papelId, string $escopoTipo, ?int $escopoId): array;

    public function removerAtribuicao(int $atribuicaoId): void;

    /**
     * Acessos com o papel Administrador no escopo da organização.
     *
     * @return list<int>
     */
    public function acessosAdministradores(?int $excetoAtribuicaoId = null): array;
}
