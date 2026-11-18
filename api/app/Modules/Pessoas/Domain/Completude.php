<?php

namespace App\Modules\Pessoas\Domain;

/**
 * Completude da ficha (CA-14; RN-13; U-05): obrigatórios do vínculo preenchidos ÷ obrigatórios do vínculo.
 * Lista vazia = 100%. Arredonda para baixo, para a ficha nunca parecer completa antes de estar.
 */
final readonly class Completude
{
    /**
     * @param  list<string>  $obrigatorios
     * @param  list<string>  $faltando
     */
    private function __construct(
        public int $percentual,
        public array $obrigatorios,
        public array $faltando,
    ) {}

    /**
     * @param  list<string>  $obrigatorios  chaves de `CampoFicha` do vínculo
     * @param  list<string>  $preenchidos  chaves de `CampoFicha` preenchidas na ficha
     */
    public static function calcular(array $obrigatorios, array $preenchidos): self
    {
        $obrigatorios = array_values(array_unique($obrigatorios));
        if ($obrigatorios === []) {
            return new self(100, [], []);
        }

        $faltando = array_values(array_diff($obrigatorios, $preenchidos));
        $feitos = count($obrigatorios) - count($faltando);

        return new self(intdiv($feitos * 100, count($obrigatorios)), $obrigatorios, $faltando);
    }
}
