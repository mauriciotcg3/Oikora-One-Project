<?php

namespace App\Modules\Autorizacao\Contracts;

use App\Modules\Autorizacao\Domain\Permissao;
use Closure;

/**
 * Alcance de quem age na requisição ou no job (OIK-ADR-0004 §4, §5). Calculado uma vez por contexto, a
 * partir das atribuições, sem cache entre requisições. Sem ator definido, toda pergunta falha com
 * `authz.context_missing` (ContextoDeAlcanceAusente).
 */
interface AlcanceAtual
{
    public function definido(): bool;

    public function ator(): Ator;

    /** @return list<int> igrejas em que a permissão vale */
    public function igrejasCom(Permissao $permissao): array;

    public function pode(Permissao $permissao, int $igrejaId): bool;

    /** A permissão vem de uma atribuição com escopo `organizacao` (governança, papéis, acessos). */
    public function temNaOrganizacao(Permissao $permissao): bool;

    /**
     * Rotina de sistema (recifra, purga): alcance de todas as igrejas da organização, sem acesso.
     * Não remove o filtro de alcance; só o define de forma explícita.
     *
     * @template T
     *
     * @param  Closure(): T  $acao
     * @return T
     */
    public function comoSistema(int $organizacaoId, Closure $acao): mixed;
}
