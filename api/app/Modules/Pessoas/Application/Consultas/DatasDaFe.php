<?php

namespace App\Modules\Pessoas\Application\Consultas;

use DateTimeImmutable;

/** Consulta própria de Pessoas; recebe somente igrejas já autorizadas e nunca decide alcance. */
interface DatasDaFe
{
    /**
     * @param  list<int>  $igrejas
     * @return list<array<string, mixed>>
     */
    public function novosConvertidos(array $igrejas): array;

    /**
     * @param  list<int>  $igrejas
     * @return list<array<string, mixed>>
     */
    public function aniversarios(array $igrejas, DateTimeImmutable $inicio, DateTimeImmutable $fim): array;
}
