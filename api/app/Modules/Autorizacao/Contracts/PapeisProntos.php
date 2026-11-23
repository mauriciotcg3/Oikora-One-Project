<?php

namespace App\Modules\Autorizacao\Contracts;

/**
 * Papéis prontos da organização (OIK-ADR-0004 §3; RN-17; plan.md §3.3), criados no provisionamento:
 * Administrador (todas as permissões, escopo organização) e Secretaria (operação de Pessoas da igreja e
 * as sensíveis documentos, contatos e fé). Roda com a organização no contexto; repetir não duplica.
 */
interface PapeisProntos
{
    /** @return array{administrador: int, secretaria: int} ids dos papéis */
    public function criar(int $organizacaoId): array;
}
