<?php

namespace App\Modules\Autorizacao\Contracts;

use App\Modules\Autorizacao\Domain\EscopoTipo;

/**
 * O que a Identidade precisa da Autorização para convidar e revogar acessos (OIK-ADR-0003 §5;
 * OIK-ADR-0004 §3): conferir o papel e o escopo do convite contra o ator (anti-escalada), criar a
 * atribuição quando o convite é aceito e manter a trava do último Administrador.
 */
interface ConcessaoDeAcesso
{
    /**
     * Confere papel e escopo do convite. Escalada bloqueada já fica auditada (`authz.escalation_blocked`).
     *
     * @return array{papel_id: int, permissoes: list<string>, escopo_tipo: string, escopo_id: int|null}|string
     *                                                                                                         código da recusa: `papel_inexistente`, `escopo_inexistente` ou `escalada_bloqueada`
     */
    public function conferir(string $papelUuid, EscopoTipo $tipo, ?string $escopoUuid): array|string;

    /**
     * Atribui o papel ao acesso recém-criado pelo aceite. Sem ator: o convite já passou pela anti-escalada
     * na emissão. Devolve o uuid da atribuição.
     */
    public function atribuir(int $organizacaoId, int $acessoId, int $papelId, EscopoTipo $tipo, ?int $escopoId): string;

    /** Revogar este acesso deixaria a organização sem Administrador ativo no escopo da organização. */
    public function ultimoAdministrador(int $acessoId): bool;

    public function nomeDoPapel(int $papelId): ?string;
}
