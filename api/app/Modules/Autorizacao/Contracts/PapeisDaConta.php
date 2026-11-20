<?php

namespace App\Modules\Autorizacao\Contracts;

/** Perguntas de Identidade sobre papéis e capacidades (RN-16; `GET /me`, T-16). */
interface PapeisDaConta
{
    /** A conta tem o papel de sistema Administrador em alguma organização (acesso ativo). Sem contexto. */
    public function administrador(int $contaId): bool;

    /**
     * Capacidades efetivas do acesso na organização do contexto (RLS): atribuições de papel mais concessões
     * temporárias vigentes, por permissão e escopo, com os identificadores públicos do escopo
     * (`contrato-permissoes.md`, bootstrap da sessão).
     *
     * @return list<array{permissao: string, escopo_tipo: string, escopo_ids: list<string>}>
     */
    public function capacidades(int $organizacaoId, int $acessoId): array;
}
