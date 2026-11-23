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

    public function uuids(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $uuids = [];
        foreach (DB::table('acessos')->whereIn('id', $ids)->get(['id', 'uuid']) as $acesso) {
            $uuids[(int) $acesso->id] = (string) $acesso->uuid;
        }

        return $uuids;
    }

    public function pessoas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $pessoas = [];
        foreach (DB::table('acessos')->whereIn('id', $ids)->get(['id', 'pessoa_id']) as $acesso) {
            $pessoas[(int) $acesso->id] = (int) $acesso->pessoa_id;
        }

        return $pessoas;
    }

    public function contasAtivas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $contas = [];
        foreach (DB::table('acessos')->whereIn('id', $ids)->where('estado', 'ativo')->get(['id', 'conta_id']) as $acesso) {
            $contas[(int) $acesso->id] = (int) $acesso->conta_id;
        }

        return $contas;
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

    public function ativoDaPessoa(int $pessoaId): ?int
    {
        $id = DB::table('acessos')->where('pessoa_id', $pessoaId)->where('estado', 'ativo')->value('id');

        return $id === null ? null : (int) $id;
    }

    public function religarPessoa(int $acessoId, int $pessoaId): void
    {
        DB::table('acessos')->where('id', $acessoId)->update(['pessoa_id' => $pessoaId, 'updated_at' => now()]);
    }

    public function revogarDaPessoa(int $pessoaId): ?int
    {
        $id = $this->ativoDaPessoa($pessoaId);
        if ($id !== null) {
            DB::table('acessos')->where('id', $id)->update(['estado' => 'revogado', 'revogado_em' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    public function pessoaTeveAcesso(int $pessoaId): bool
    {
        return DB::table('acessos')->where('pessoa_id', $pessoaId)->exists();
    }
}
