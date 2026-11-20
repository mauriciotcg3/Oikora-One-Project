<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\Permissao;

/**
 * "Ninguém concede permissão ou escopo que não tem" (OIK-ADR-0004 §3; RN-17).
 * - Em papel: cada permissão acrescentada precisa estar entre as do ator.
 * - Em atribuição: o ator precisa ter cada permissão do papel cobrindo o escopo concedido — organização
 *   só com escopo organização; regional com organização ou a mesma regional; igreja se ela está no alcance.
 */
final readonly class AntiEscalada
{
    public function __construct(private AlcanceDaRequisicao $alcance) {}

    /**
     * @param  list<string>  $permissoes
     * @return list<string> as que o ator não tem
     */
    public function permissoesQueFaltam(array $permissoes): array
    {
        $doAtor = array_unique(array_column($this->alcance->atribuicoes(), 'permissao'));

        return array_values(array_diff($permissoes, $doAtor));
    }

    /**
     * @param  list<string>  $permissoes
     * @return list<string> as que o ator não tem no escopo pedido
     */
    public function semCobertura(array $permissoes, EscopoTipo $tipo, ?int $escopoId): array
    {
        $faltam = [];

        foreach ($permissoes as $permissao) {
            if (! $this->cobre(Permissao::from($permissao), $tipo, $escopoId)) {
                $faltam[] = $permissao;
            }
        }

        return $faltam;
    }

    private function cobre(Permissao $permissao, EscopoTipo $tipo, ?int $escopoId): bool
    {
        if ($this->alcance->temNaOrganizacao($permissao)) {
            return true;
        }

        return match ($tipo) {
            EscopoTipo::Organizacao => false,
            EscopoTipo::Regional => $this->temAtribuicao($permissao, EscopoTipo::Regional, $escopoId),
            EscopoTipo::Igreja => $escopoId !== null && $this->alcance->pode($permissao, $escopoId),
        };
    }

    private function temAtribuicao(Permissao $permissao, EscopoTipo $tipo, ?int $escopoId): bool
    {
        foreach ($this->alcance->atribuicoes() as $atribuicao) {
            if ($atribuicao['permissao'] === $permissao->value
                && $atribuicao['escopo_tipo'] === $tipo->value
                && $atribuicao['escopo_id'] === $escopoId) {
                return true;
            }
        }

        return false;
    }
}
