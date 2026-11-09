<?php

namespace App\Modules\Organizacao\Infrastructure;

use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Shared\Tenancy\Contracts\OrganizacaoDisponivel;

final class ConsultaOrganizacoesPostgres implements ConsultaOrganizacoes, OrganizacaoDisponivel
{
    public function verificar(int $organizacaoId): bool
    {
        return Organizacao::query()
            ->whereKey($organizacaoId)
            ->whereIn('estado', ['ativa', 'em_migracao'])
            ->exists();
    }

    public function idsDisponiveis(): array
    {
        /** @var list<int> $ids */
        $ids = Organizacao::query()
            ->whereIn('estado', ['ativa', 'em_migracao'])
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }
}
