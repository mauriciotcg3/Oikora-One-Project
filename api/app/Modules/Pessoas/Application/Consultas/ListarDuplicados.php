<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Application\Mesclagem\Mesclagens;

/**
 * Possíveis duplicados (P FR-016; RN-23; CA-21): pares entre pessoas que quem consulta vê, fora da
 * lixeira e sem os marcados como "não duplicados", com o critério que casou. `mesclar` aparece em
 * `meta.acoes` só quando quem consulta tem `pessoas.mesclar` nas igrejas das duas.
 */
final readonly class ListarDuplicados
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Mesclagens $mesclagens,
        private CatalogoIgrejas $igrejas,
    ) {}

    public function permitido(): bool
    {
        return $this->alcance->igrejasCom(Permissao::PessoasVer) !== [];
    }

    /** @return array{itens: list<array<string, mixed>>, total: int} */
    public function executar(int $pagina, int $porPagina): array
    {
        $pares = $this->mesclagens->pares($pagina, $porPagina);
        $ids = array_values(array_unique([...array_column($pares['itens'], 'a'), ...array_column($pares['itens'], 'b')]));
        $pessoas = $this->mesclagens->resumos($ids);
        $igrejas = $this->igrejas->resumo(array_values(array_unique(array_column($pessoas, 'igreja_id'))));

        $resumo = static fn (array $pessoa): array => [
            'uuid' => $pessoa['uuid'],
            'nome' => $pessoa['nome'],
            'nascimento' => $pessoa['nascimento'],
            'vinculo' => $pessoa['vinculo'],
            'igreja' => $igrejas[$pessoa['igreja_id']] ?? null,
        ];

        $itens = [];
        foreach ($pares['itens'] as $par) {
            $a = $pessoas[$par['a']] ?? null;
            $b = $pessoas[$par['b']] ?? null;
            if ($a === null || $b === null) {
                continue;
            }
            $podeMesclar = $this->alcance->pode(Permissao::PessoasMesclar, $a['igreja_id'])
                && $this->alcance->pode(Permissao::PessoasMesclar, $b['igreja_id']);
            $itens[] = [
                'pessoas' => [$resumo($a), $resumo($b)],
                'criterios' => $par['criterios'],
                'meta' => ['acoes' => $podeMesclar ? ['mesclar', 'nao_duplicadas'] : []],
            ];
        }

        return ['itens' => $itens, 'total' => $pares['total']];
    }
}
