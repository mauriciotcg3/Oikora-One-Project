<?php

namespace App\Modules\Pessoas\Infrastructure\Consultas;

use App\Modules\Pessoas\Application\Consultas\DatasDaFe;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class DatasDaFePostgres implements DatasDaFe
{
    /**
     * @param  list<int>  $igrejas
     * @return list<array<string, mixed>>
     */
    public function novosConvertidos(array $igrejas): array
    {
        if ($igrejas === []) {
            return [];
        }

        return array_values(Pessoa::query()->whereIn('igreja_id', $igrejas)->whereNull('excluida_em')->where('novo_convertido', true)
            ->orderByDesc('conversao_data')->orderBy('nome')
            ->get(['uuid', 'nome', 'conversao_data', 'conversao_canal'])
            ->map(static fn (Pessoa $p): array => [
                'uuid' => $p->uuid, 'nome' => $p->nome,
                'conversao_data' => $p->getAttribute('conversao_data')?->format('Y-m-d'),
                'conversao_canal' => $p->getAttribute('conversao_canal'),
            ])->values()->all());
    }

    /**
     * @param  list<int>  $igrejas
     * @return list<array<string, mixed>>
     */
    public function aniversarios(array $igrejas, DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        if ($igrejas === []) {
            return [];
        }
        $dias = [];
        for ($dia = $inicio; $dia <= $fim; $dia = $dia->modify('+1 day')) {
            $dias[] = $dia->format('m-d');
        }

        $consulta = Pessoa::query()->whereIn('igreja_id', $igrejas)->whereNull('excluida_em')
            ->where(function (Builder $q) use ($dias): void {
                foreach (['nascimento', 'batismo_data', 'data_casamento'] as $campo) {
                    $q->orWhere(function (Builder $datas) use ($campo, $dias): void {
                        foreach ($dias as $dia) {
                            [$mes, $numero] = array_map('intval', explode('-', $dia));
                            $datas->orWhere(fn (Builder $data) => $data->whereMonth($campo, $mes)->whereDay($campo, $numero));
                        }
                    });
                }
            })->get(['uuid', 'nome', 'nascimento', 'batismo_data', 'data_casamento']);

        $itens = [];
        foreach ($consulta as $pessoa) {
            foreach (['nascimento', 'batismo', 'casamento'] as $tipo) {
                $campo = match ($tipo) {
                    'batismo' => 'batismo_data',
                    'casamento' => 'data_casamento',
                    default => 'nascimento',
                };
                $data = $pessoa->getAttribute($campo);
                if ($data !== null && in_array($data->format('m-d'), $dias, true)) {
                    $itens[] = ['uuid' => $pessoa->uuid, 'nome' => $pessoa->nome, 'tipo' => $tipo, 'data' => $data->format('Y-m-d')];
                }
            }
        }
        usort($itens, static fn (array $a, array $b): int => strcmp(substr($a['data'], 5).$a['nome'].$a['tipo'], substr($b['data'], 5).$b['nome'].$b['tipo']));

        return $itens;
    }
}
