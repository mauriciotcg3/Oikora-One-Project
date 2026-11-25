<?php

namespace App\Modules\Pessoas\Application\Exportacoes;

interface ArquivosDeExportacao
{
    /**
     * @param  list<string>  $cabecalhos
     * @param  list<list<string|int|bool|null>>  $linhas
     */
    public function gravar(int $organizacaoId, string $uuid, string $formato, array $cabecalhos, array $linhas): string;

    public function url(string $caminho): ?string;

    public function apagar(string $caminho): void;
}
