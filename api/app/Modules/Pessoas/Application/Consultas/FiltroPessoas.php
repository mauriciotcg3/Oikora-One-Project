<?php

namespace App\Modules\Pessoas\Application\Consultas;

/**
 * Filtros da lista de pessoas (U-17; plan.md §7) já resolvidos para ids internos. Um filtro com uuid que
 * não existe vira `semResultado`: a lista vem vazia, sem dizer se o uuid existe fora do alcance.
 */
final readonly class FiltroPessoas
{
    public const ORDENS = ['nome', '-nome', 'nascimento', '-nascimento', 'entrada_em', '-entrada_em', 'cadastro', '-cadastro'];

    /**
     * @param  array<string, list<string>>  $obrigatoriosPorVinculo  só com `fichaIncompleta`
     */
    public function __construct(
        public int $pagina = 1,
        public int $porPagina = 25,
        public string $ordem = 'nome',
        public ?int $igrejaId = null,
        public ?string $vinculo = null,
        public ?string $situacao = null,
        public ?int $tipoMembroId = null,
        public ?int $funcaoId = null,
        public bool $fichaIncompleta = false,
        public ?int $mesAniversario = null,
        public array $obrigatoriosPorVinculo = [],
        public bool $semResultado = false,
    ) {}
}
