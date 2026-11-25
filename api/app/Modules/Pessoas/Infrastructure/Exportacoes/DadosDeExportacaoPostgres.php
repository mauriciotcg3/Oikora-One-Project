<?php

namespace App\Modules\Pessoas\Infrastructure\Exportacoes;

use App\Modules\Pessoas\Application\Exportacoes\DadosDeExportacao;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use Illuminate\Database\Eloquent\Builder;

final class DadosDeExportacaoPostgres implements DadosDeExportacao
{
    public function obter(string $tipo, array $filtro, array $selecionados, array $categorias, array $igrejasExportaveis, array $igrejasContatos, array $igrejasFe): array
    {
        $consulta = Pessoa::query()->whereNull('excluida_em')->whereIn('igreja_id', $igrejasExportaveis);
        if ($selecionados !== []) {
            $consulta->whereIn('uuid', $selecionados);
        }
        $consulta->when(isset($filtro['igreja_id']), fn (Builder $q) => $q->where('igreja_id', (int) $filtro['igreja_id']))
            ->when(isset($filtro['vinculo']), fn (Builder $q) => $q->where('vinculo', $filtro['vinculo']))
            ->when(isset($filtro['situacao']), fn (Builder $q) => $q->where('situacao', $filtro['situacao']));
        if ($tipo === 'novos_convertidos') {
            $consulta->where('novo_convertido', true);
        } elseif ($tipo === 'aniversarios') {
            $mes = (int) ($filtro['mes'] ?? now()->month);
            $consulta->where(fn (Builder $q) => $q->whereMonth('nascimento', $mes)->orWhereMonth('conversao_data', $mes)->orWhereMonth('batismo_data', $mes));
        }

        $comContatos = in_array('contatos', $categorias, true) && $igrejasContatos !== [];
        $comFe = in_array('fe', $categorias, true) && $igrejasFe !== [];
        $colunas = ['id', 'uuid', 'nome', 'igreja_id', 'vinculo', 'situacao', 'nascimento', 'matricula', 'entrada_em'];
        if ($comFe) {
            array_push($colunas, 'aceitou_jesus', 'conversao_data', 'conversao_canal', 'batismo_data', 'batizado_por', 'igreja_origem', 'novo_convertido');
        }
        $consulta->select($colunas);
        if ($comContatos) {
            foreach (['celular', 'email'] as $contato) {
                $consulta->selectSub("select c.valor from pessoa_contatos c where c.pessoa_id = pessoas.id and c.tipo = '{$contato}' order by c.principal desc, c.id limit 1", $contato);
            }
        }
        $cabecalhos = ['uuid', 'nome', 'igreja_id', 'vinculo', 'situacao', 'nascimento', 'matricula', 'entrada_em'];
        if ($comContatos) {
            array_push($cabecalhos, 'celular', 'email');
        }
        if ($comFe) {
            array_push($cabecalhos, 'aceitou_jesus', 'conversao_data', 'conversao_canal', 'batismo_data', 'batizado_por', 'igreja_origem', 'novo_convertido');
        }
        $linhas = [];
        foreach ($consulta->orderBy('nome_busca')->orderBy('id')->get() as $pessoa) {
            $linha = [
                (string) $pessoa->getAttribute('uuid'),
                (string) $pessoa->getAttribute('nome'),
                (int) $pessoa->getAttribute('igreja_id'),
                $this->texto($pessoa->getAttribute('vinculo')),
                $this->texto($pessoa->getAttribute('situacao')),
                $this->data($pessoa->getAttribute('nascimento')),
                $this->texto($pessoa->getAttribute('matricula')),
                $this->data($pessoa->getAttribute('entrada_em')),
            ];
            if ($comContatos) {
                $permitido = in_array((int) $pessoa->getAttribute('igreja_id'), $igrejasContatos, true);
                array_push(
                    $linha,
                    $permitido ? $this->texto($pessoa->getAttribute('celular')) : null,
                    $permitido ? $this->texto($pessoa->getAttribute('email')) : null,
                );
            }
            if ($comFe) {
                $permitido = in_array((int) $pessoa->getAttribute('igreja_id'), $igrejasFe, true);
                array_push($linha,
                    $permitido ? (bool) $pessoa->getAttribute('aceitou_jesus') : null,
                    $permitido ? $this->data($pessoa->getAttribute('conversao_data')) : null,
                    $permitido ? $this->texto($pessoa->getAttribute('conversao_canal')) : null,
                    $permitido ? $this->data($pessoa->getAttribute('batismo_data')) : null,
                    $permitido ? $this->texto($pessoa->getAttribute('batizado_por')) : null,
                    $permitido ? $this->texto($pessoa->getAttribute('igreja_origem')) : null,
                    $permitido ? (bool) $pessoa->getAttribute('novo_convertido') : null,
                );
            }
            $linhas[] = $linha;
        }

        return ['cabecalhos' => $cabecalhos, 'linhas' => $linhas];
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private function data(mixed $valor): ?string
    {
        return $valor instanceof \DateTimeInterface ? $valor->format('Y-m-d') : $this->texto($valor);
    }
}
