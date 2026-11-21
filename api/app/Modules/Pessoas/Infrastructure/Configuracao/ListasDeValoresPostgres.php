<?php

namespace App\Modules\Pessoas\Infrastructure\Configuracao;

use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Domain\ListaValores;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Consultas sob a RLS da organização em contexto.
 *
 * @phpstan-import-type ItemLista from ListasDeValores
 */
final class ListasDeValoresPostgres implements ListasDeValores
{
    private const COLUNAS = ['id', 'uuid', 'lista', 'nome', 'ativo', 'ordem'];

    public function listar(ListaValores $lista, bool $soAtivos = false): array
    {
        return array_values(DB::table('listas_valores')
            ->where('lista', $lista->value)
            ->when($soAtivos, fn ($consulta) => $consulta->where('ativo', true))
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(self::COLUNAS)
            ->map(fn (stdClass $linha): array => $this->item($linha))
            ->all());
    }

    public function porUuid(ListaValores $lista, string $uuid): ?array
    {
        $linha = DB::table('listas_valores')->where('lista', $lista->value)->where('uuid', $uuid)->first(self::COLUNAS);

        return $linha === null ? null : $this->item($linha);
    }

    public function nomeEmUso(ListaValores $lista, string $nome, ?int $excetoId = null): bool
    {
        return DB::table('listas_valores')
            ->where('lista', $lista->value)
            ->whereRaw('lower(nome) = lower(?)', [$nome])
            ->when($excetoId !== null, fn ($consulta) => $consulta->where('id', '<>', $excetoId))
            ->exists();
    }

    public function criar(int $organizacaoId, ListaValores $lista, string $nome, ?int $ordem): array
    {
        $ordem ??= (int) DB::table('listas_valores')->where('lista', $lista->value)->max('ordem') + 1;

        $id = (int) DB::table('listas_valores')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'lista' => $lista->value,
            'nome' => $nome,
            'ativo' => true,
            'ordem' => $ordem,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->porId($id);
    }

    public function atualizar(int $id, array $mudancas): array
    {
        if ($mudancas !== []) {
            DB::table('listas_valores')->where('id', $id)->update([...$mudancas, 'updated_at' => now()]);
        }

        return $this->porId($id);
    }

    public function idAtivo(ListaValores $lista, string $uuid): ?int
    {
        $id = DB::table('listas_valores')
            ->where('lista', $lista->value)
            ->where('uuid', $uuid)
            ->where('ativo', true)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function nomes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $nomes = [];
        foreach (DB::table('listas_valores')->whereIn('id', $ids)->get(['id', 'nome']) as $linha) {
            $nomes[(int) $linha->id] = (string) $linha->nome;
        }

        return $nomes;
    }

    public function uuids(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $uuids = [];
        foreach (DB::table('listas_valores')->whereIn('id', $ids)->get(['id', 'uuid']) as $linha) {
            $uuids[(int) $linha->id] = (string) $linha->uuid;
        }

        return $uuids;
    }

    /** @return ItemLista */
    private function porId(int $id): array
    {
        $linha = DB::table('listas_valores')->where('id', $id)->first(self::COLUNAS);
        assert($linha instanceof stdClass);

        return $this->item($linha);
    }

    /** @return ItemLista */
    private function item(stdClass $linha): array
    {
        return [
            'id' => (int) $linha->id,
            'uuid' => (string) $linha->uuid,
            'lista' => (string) $linha->lista,
            'nome' => (string) $linha->nome,
            'ativo' => (bool) $linha->ativo,
            'ordem' => (int) $linha->ordem,
        ];
    }
}
