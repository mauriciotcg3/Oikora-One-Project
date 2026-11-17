<?php

namespace App\Modules\Pessoas\Application\Configuracao;

use App\Modules\Pessoas\Domain\Vinculo;

/** Obrigatórios por vínculo da organização em contexto (RN-13). Lista vazia = ficha 100% completa (U-05). */
interface CamposObrigatorios
{
    /** @return array<string, list<string>> chaves do catálogo por vínculo, em ordem alfabética */
    public function todos(): array;

    /** @return list<string> */
    public function doVinculo(Vinculo $vinculo): array;

    /** @param  list<string>  $campos  chaves de `CampoFicha`, já validadas */
    public function definir(int $organizacaoId, Vinculo $vinculo, array $campos): void;
}
