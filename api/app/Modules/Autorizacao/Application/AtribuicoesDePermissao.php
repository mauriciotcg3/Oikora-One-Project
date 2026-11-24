<?php

namespace App\Modules\Autorizacao\Application;

interface AtribuicoesDePermissao
{
    /**
     * Permissões efetivas do acesso: atribuições de papel mais concessões temporárias vigentes agora
     * (OIK-ADR-0012 §7, §10). Vencida ou revogada deixa de contar na leitura seguinte, sem job nem cache.
     *
     * @return list<array{permissao: string, escopo_tipo: string, escopo_id: int|null}>
     */
    public function doAcesso(int $organizacaoId, int $acessoId): array;

    /** O acesso tem ao menos uma atribuição de papel (concessão sozinha não conta, OIK-ADR-0004 §13). */
    public function temPapel(int $organizacaoId, int $acessoId): bool;

    /**
     * Acessos da organização com a permissão em algum escopo, por papel ou concessão vigente. O escopo
     * é conferido depois pelo `Alcance`.
     *
     * @return list<int>
     */
    public function acessosComPermissao(int $organizacaoId, string $permissao): array;
}
