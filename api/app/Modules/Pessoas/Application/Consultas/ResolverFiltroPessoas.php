<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Application\Configuracao\CamposObrigatorios;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Domain\ListaValores;
use App\Modules\Pessoas\Domain\Vinculo;

/** Resolve os identificadores públicos e a configuração usados pelo filtro comum da lista e da exportação. */
final readonly class ResolverFiltroPessoas
{
    public function __construct(
        private CatalogoIgrejas $igrejas,
        private ListasDeValores $listas,
        private CamposObrigatorios $obrigatorios,
    ) {}

    /**
     * @param  array{igreja?: string, vinculo?: string, situacao?: string, tipo_membro?: string, funcao?: string, ficha_incompleta?: bool, aniversariantes_mes?: int}  $filtros
     */
    public function resolver(array $filtros, int $pagina = 1, int $porPagina = 25, string $ordem = 'nome'): FiltroPessoas
    {
        $semResultado = false;
        $igrejaId = null;
        if (isset($filtros['igreja'])) {
            $igrejaId = $this->igrejas->igrejaPorUuid($filtros['igreja']);
            $semResultado = $igrejaId === null;
        }
        $tipoMembroId = $this->item(ListaValores::TipoMembro, $filtros['tipo_membro'] ?? null, $semResultado);
        $funcaoId = $this->item(ListaValores::Funcao, $filtros['funcao'] ?? null, $semResultado);

        $incompleta = (bool) ($filtros['ficha_incompleta'] ?? false);
        $obrigatorios = [];
        if ($incompleta) {
            foreach (Vinculo::cases() as $vinculo) {
                $obrigatorios[$vinculo->value] = $this->obrigatorios->doVinculo($vinculo);
            }
        }

        return new FiltroPessoas(
            pagina: $pagina,
            porPagina: $porPagina,
            ordem: $ordem,
            igrejaId: $igrejaId,
            vinculo: $filtros['vinculo'] ?? null,
            situacao: $filtros['situacao'] ?? null,
            tipoMembroId: $tipoMembroId,
            funcaoId: $funcaoId,
            fichaIncompleta: $incompleta,
            mesAniversario: $filtros['aniversariantes_mes'] ?? null,
            obrigatoriosPorVinculo: $obrigatorios,
            semResultado: $semResultado,
        );
    }

    private function item(ListaValores $lista, ?string $uuid, bool &$semResultado): ?int
    {
        if ($uuid === null) {
            return null;
        }
        $item = $this->listas->porUuid($lista, $uuid);
        if ($item === null) {
            $semResultado = true;

            return null;
        }

        return (int) $item['id'];
    }
}
