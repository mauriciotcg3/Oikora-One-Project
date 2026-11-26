<?php

namespace App\Modules\Pessoas\Infrastructure\Exportacoes;

use App\Modules\Pessoas\Application\Exportacoes\ArquivosDeExportacao;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

final class ArquivosOpenSpout implements ArquivosDeExportacao
{
    /**
     * @param  list<string>  $cabecalhos
     * @param  list<list<string|int|bool|null>>  $linhas
     */
    public function gravar(int $organizacaoId, string $uuid, string $formato, array $cabecalhos, array $linhas): string
    {
        $temporario = tempnam(sys_get_temp_dir(), 'oikora-export-');
        if ($temporario === false) {
            throw new \RuntimeException('exportacao.temporario_indisponivel');
        }
        $writer = $formato === 'csv' ? new CsvWriter : new XlsxWriter;
        try {
            $writer->openToFile($temporario);
            $writer->addRow(Row::fromValues($cabecalhos));
            foreach ($linhas as $linha) {
                $writer->addRow(Row::fromValues($linha));
            }
            $writer->close();
            $caminho = "organizacoes/{$organizacaoId}/exportacoes/{$uuid}.{$formato}";
            $fluxo = fopen($temporario, 'rb');
            if ($fluxo === false) {
                throw new \RuntimeException('exportacao.temporario_indisponivel');
            }
            try {
                Storage::disk((string) config('oikora.arquivos.disco', 'arquivos'))->writeStream($caminho, $fluxo);
            } finally {
                fclose($fluxo);
            }

            return $caminho;
        } finally {
            if (is_file($temporario)) {
                unlink($temporario);
            }
        }
    }

    public function url(string $caminho): ?string
    {
        $disco = Storage::disk((string) config('oikora.arquivos.disco', 'arquivos'));

        return $disco->exists($caminho) ? $disco->temporaryUrl($caminho, now()->addMinutes((int) config('oikora.arquivos.validade_minutos', 5))) : null;
    }

    public function apagar(string $caminho): void
    {
        Storage::disk((string) config('oikora.arquivos.disco', 'arquivos'))->delete($caminho);
    }
}
