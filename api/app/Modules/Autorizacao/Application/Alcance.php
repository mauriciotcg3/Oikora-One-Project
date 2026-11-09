<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use RuntimeException;

final readonly class Alcance
{
    public function __construct(
        private AtribuicoesDePermissao $atribuicoes,
        private CatalogoIgrejas $igrejas,
    ) {}

    /** @return array<string, list<int>> */
    public function calcular(int $organizacaoId, int $acessoId): array
    {
        $alcance = [];

        foreach ($this->atribuicoes->doAcesso($organizacaoId, $acessoId) as $atribuicao) {
            $tipo = EscopoTipo::from($atribuicao['escopo_tipo']);
            $ids = match ($tipo) {
                EscopoTipo::Organizacao => $this->igrejas->idsDaOrganizacao($organizacaoId),
                EscopoTipo::Regional => $this->igrejas->idsDaRegional(
                    $organizacaoId,
                    $atribuicao['escopo_id'] ?? throw new RuntimeException('Escopo regional sem identificador.'),
                ),
                EscopoTipo::Igreja => $this->igrejas->pertenceAOrganizacao(
                    $organizacaoId,
                    $atribuicao['escopo_id'] ?? throw new RuntimeException('Escopo de igreja sem identificador.'),
                ) ? [$atribuicao['escopo_id']] : [],
            };

            $permissao = Permissao::from($atribuicao['permissao'])->value;
            $alcance[$permissao] = array_values(array_unique([
                ...($alcance[$permissao] ?? []),
                ...$ids,
            ]));
            sort($alcance[$permissao]);
        }

        return $alcance;
    }

    public function permite(int $organizacaoId, int $acessoId, Permissao $permissao, int $igrejaId): bool
    {
        return in_array($igrejaId, $this->calcular($organizacaoId, $acessoId)[$permissao->value] ?? [], true);
    }
}
