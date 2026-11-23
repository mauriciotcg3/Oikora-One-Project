<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\QuemPode;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\Permissao;

/** Os candidatos com a permissão em algum escopo, filtrados pelo mesmo cálculo de alcance do painel. */
final readonly class QuemPodePeloAlcance implements QuemPode
{
    public function __construct(
        private AlcanceAtual $alcanceAtual,
        private AtribuicoesDePermissao $atribuicoes,
        private Alcance $alcance,
    ) {}

    public function naIgreja(Permissao $permissao, int $igrejaId): array
    {
        $organizacaoId = $this->alcanceAtual->ator()->organizacaoId;

        return array_values(array_filter(
            $this->atribuicoes->acessosComPermissao($organizacaoId, $permissao->value),
            fn (int $acessoId): bool => $this->alcance->permite($organizacaoId, $acessoId, $permissao, $igrejaId),
        ));
    }

    public function naOrganizacao(Permissao $permissao, int $organizacaoId): array
    {
        return array_values(array_filter(
            $this->atribuicoes->acessosComPermissao($organizacaoId, $permissao->value),
            fn (int $acessoId): bool => array_filter(
                $this->atribuicoes->doAcesso($organizacaoId, $acessoId),
                static fn (array $atribuicao): bool => $atribuicao['permissao'] === $permissao->value
                    && $atribuicao['escopo_tipo'] === EscopoTipo::Organizacao->value,
            ) !== [],
        ));
    }
}
