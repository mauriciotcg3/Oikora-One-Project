<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\Ator;
use App\Modules\Autorizacao\Contracts\ContextoDeAlcanceAusente;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use Closure;

/**
 * Registrado como `scoped`: uma instância por requisição ou job. O middleware do painel define o ator;
 * o alcance é calculado na primeira pergunta e vale até o fim do contexto.
 */
final class AlcanceDaRequisicao implements AlcanceAtual
{
    private ?Ator $ator = null;

    /** Organização da rotina de sistema em curso, quando houver. */
    private ?int $sistema = null;

    /** @var array<string, list<int>>|null */
    private ?array $alcance = null;

    /** @var list<array{permissao: string, escopo_tipo: string, escopo_id: int|null}>|null */
    private ?array $atribuicoes = null;

    public function __construct(
        private readonly Alcance $calculo,
        private readonly AtribuicoesDePermissao $fonte,
        private readonly CatalogoIgrejas $igrejas,
    ) {}

    public function definir(Ator $ator): void
    {
        $this->ator = $ator;
        $this->sistema = null;
        $this->alcance = null;
        $this->atribuicoes = null;
    }

    public function limpar(): void
    {
        $this->ator = null;
        $this->sistema = null;
        $this->alcance = null;
        $this->atribuicoes = null;
    }

    public function definido(): bool
    {
        return $this->ator !== null || $this->sistema !== null;
    }

    public function ator(): Ator
    {
        return $this->ator ?? throw new ContextoDeAlcanceAusente;
    }

    public function igrejasCom(Permissao $permissao): array
    {
        return $this->alcance()[$permissao->value] ?? [];
    }

    public function pode(Permissao $permissao, int $igrejaId): bool
    {
        return in_array($igrejaId, $this->igrejasCom($permissao), true);
    }

    public function temNaOrganizacao(Permissao $permissao): bool
    {
        if ($this->sistema !== null) {
            return true;
        }

        foreach ($this->atribuicoes() as $atribuicao) {
            if ($atribuicao['permissao'] === $permissao->value && $atribuicao['escopo_tipo'] === EscopoTipo::Organizacao->value) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{permissao: string, escopo_tipo: string, escopo_id: int|null}> */
    public function atribuicoes(): array
    {
        $ator = $this->ator();

        return $this->atribuicoes ??= $this->fonte->doAcesso($ator->organizacaoId, $ator->acessoId);
    }

    public function comoSistema(int $organizacaoId, Closure $acao): mixed
    {
        $anterior = [$this->ator, $this->sistema, $this->alcance, $this->atribuicoes];
        $this->ator = null;
        $this->atribuicoes = null;
        $this->sistema = $organizacaoId;
        $todas = $this->igrejas->idsDaOrganizacao($organizacaoId);
        $this->alcance = array_fill_keys(array_map(static fn (Permissao $p): string => $p->value, Permissao::cases()), $todas);

        try {
            return $acao();
        } finally {
            [$this->ator, $this->sistema, $this->alcance, $this->atribuicoes] = $anterior;
        }
    }

    /** @return array<string, list<int>> */
    private function alcance(): array
    {
        if ($this->alcance !== null) {
            return $this->alcance;
        }

        $ator = $this->ator();

        return $this->alcance = $this->calculo->calcular($ator->organizacaoId, $ator->acessoId);
    }
}
