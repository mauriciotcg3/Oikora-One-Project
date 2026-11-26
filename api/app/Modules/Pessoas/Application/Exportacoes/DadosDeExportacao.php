<?php

namespace App\Modules\Pessoas\Application\Exportacoes;

interface DadosDeExportacao
{
    /**
     * @param  array<string, mixed>  $filtro
     * @param  list<string>  $selecionados
     * @param  list<string>  $categorias
     * @param  list<int>  $igrejasExportaveis
     * @param  list<int>  $igrejasContatos
     * @param  list<int>  $igrejasFe
     * @return array{cabecalhos: list<string>, linhas: list<list<string|int|bool|null>>}
     */
    public function obter(string $tipo, array $filtro, array $selecionados, array $categorias, array $igrejasExportaveis, array $igrejasContatos, array $igrejasFe): array;
}
