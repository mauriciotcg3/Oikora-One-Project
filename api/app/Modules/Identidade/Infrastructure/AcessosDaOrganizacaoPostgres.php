<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use Illuminate\Support\Facades\DB;

/** Consulta sob a RLS: só enxerga os acessos da organização em contexto. */
final class AcessosDaOrganizacaoPostgres implements AcessosDaOrganizacao
{
    public function ativoDaConta(int $contaId): ?int
    {
        $id = DB::table('acessos')->where('conta_id', $contaId)->where('estado', 'ativo')->value('id');

        return $id === null ? null : (int) $id;
    }

    public function porUuid(string $uuid): ?array
    {
        $acesso = DB::table('acessos')->where('uuid', $uuid)->first(['id', 'estado']);

        return $acesso === null ? null : ['id' => (int) $acesso->id, 'ativo' => $acesso->estado === 'ativo'];
    }

    public function ativos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(DB::table('acessos')
            ->whereIn('id', $ids)
            ->where('estado', 'ativo')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all());
    }
}
