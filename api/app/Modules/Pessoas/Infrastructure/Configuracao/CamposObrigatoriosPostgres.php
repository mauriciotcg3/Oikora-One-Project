<?php

namespace App\Modules\Pessoas\Infrastructure\Configuracao;

use App\Modules\Pessoas\Application\Configuracao\CamposObrigatorios;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Support\Facades\DB;

/** Consultas sob a RLS da organização em contexto. */
final class CamposObrigatoriosPostgres implements CamposObrigatorios
{
    public function todos(): array
    {
        $todos = [];
        foreach (Vinculo::cases() as $vinculo) {
            $todos[$vinculo->value] = [];
        }

        foreach (DB::table('campos_obrigatorios')->orderBy('campo')->get(['vinculo', 'campo']) as $linha) {
            $todos[(string) $linha->vinculo][] = (string) $linha->campo;
        }

        return $todos;
    }

    public function doVinculo(Vinculo $vinculo): array
    {
        return array_values(DB::table('campos_obrigatorios')
            ->where('vinculo', $vinculo->value)
            ->orderBy('campo')
            ->pluck('campo')
            ->map(fn (mixed $campo): string => (string) $campo)
            ->all());
    }

    public function definir(int $organizacaoId, Vinculo $vinculo, array $campos): void
    {
        DB::table('campos_obrigatorios')->where('vinculo', $vinculo->value)->delete();

        DB::table('campos_obrigatorios')->insert(array_map(static fn (string $campo): array => [
            'organizacao_id' => $organizacaoId,
            'vinculo' => $vinculo->value,
            'campo' => $campo,
            'created_at' => now(),
            'updated_at' => now(),
        ], $campos));
    }
}
