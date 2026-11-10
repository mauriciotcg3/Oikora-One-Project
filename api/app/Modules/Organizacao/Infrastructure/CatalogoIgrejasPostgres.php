<?php

namespace App\Modules\Organizacao\Infrastructure;

use App\Modules\Organizacao\Contracts\CatalogoIgrejas;

final class CatalogoIgrejasPostgres implements CatalogoIgrejas
{
    public function idsDaOrganizacao(int $organizacaoId): array
    {
        return $this->ids(Igreja::query()->where('organizacao_id', $organizacaoId)->pluck('id')->all());
    }

    public function idsDaRegional(int $organizacaoId, int $regionalId): array
    {
        return $this->ids(Igreja::query()
            ->where('organizacao_id', $organizacaoId)
            ->where('regional_id', $regionalId)
            ->pluck('id')
            ->all());
    }

    public function pertenceAOrganizacao(int $organizacaoId, int $igrejaId): bool
    {
        return Igreja::query()
            ->where('organizacao_id', $organizacaoId)
            ->whereKey($igrejaId)
            ->exists();
    }

    public function igrejaPorUuid(string $uuid): ?int
    {
        $id = Igreja::query()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function regionalPorUuid(string $uuid): ?int
    {
        $id = Regional::query()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function resumo(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $resumo = [];
        foreach (Igreja::query()->whereKey($ids)->get(['id', 'uuid', 'nome']) as $igreja) {
            $resumo[(int) $igreja->id] = ['uuid' => (string) $igreja->uuid, 'nome' => (string) $igreja->nome];
        }

        return $resumo;
    }

    /** @param array<int, mixed> $ids
     * @return list<int>
     */
    private function ids(array $ids): array
    {
        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }
}
