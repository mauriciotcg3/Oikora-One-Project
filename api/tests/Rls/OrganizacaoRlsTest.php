<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function criarUnidadeOrganizacional(string $tabela, int $organizacaoId, string $nome): int
{
    return noContexto($organizacaoId, null, fn (): int => (int) DB::table($tabela)->insertGetId([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $organizacaoId,
        'nome' => $nome,
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

beforeEach(function (): void {
    $this->orgA = criarOrganizacao('Organização A');
    $this->orgB = criarOrganizacao('Organização B');
});

it('inclui regionais e igrejas no catálogo da RLS', function (): void {
    $tabelas = collect(DB::connection('pgsql_dono')->select(<<<'SQL'
        select c.table_name from information_schema.columns c
        join information_schema.tables t using (table_schema, table_name)
        where c.table_schema = 'public' and c.column_name = 'organizacao_id' and t.table_type = 'BASE TABLE'
    SQL))->pluck('table_name')->all();

    expect($tabelas)->toContain('regionais', 'igrejas');
});

it('impede ler, alterar e excluir unidades de outra organização', function (string $tabela): void {
    $linhaDeB = criarUnidadeOrganizacional($tabela, $this->orgB, 'Unidade B');

    noContexto($this->orgA, null, function () use ($linhaDeB, $tabela): void {
        expect(DB::table($tabela)->where('id', $linhaDeB)->count())->toBe(0)
            ->and(DB::table($tabela)->where('id', $linhaDeB)->update(['nome' => 'Invadida']))->toBe(0)
            ->and(DB::table($tabela)->where('id', $linhaDeB)->delete())->toBe(0);
    });

    noContexto($this->orgB, null, fn () => expect(DB::table($tabela)->where('id', $linhaDeB)->value('nome'))->toBe('Unidade B'));
})->with(['regionais', 'igrejas']);

it('impede inserir unidade em nome de outra organização', function (string $tabela): void {
    noContexto($this->orgA, null, fn () => DB::table($tabela)->insert([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $this->orgB,
        'nome' => 'Invadida',
        'created_at' => now(),
        'updated_at' => now(),
    ]));
})->with(['regionais', 'igrejas'])->throws(QueryException::class, 'row-level security');

it('recusa regional de outra organização na igreja', function (): void {
    $regionalDeB = criarUnidadeOrganizacional('regionais', $this->orgB, 'Regional B');

    noContexto($this->orgA, null, fn () => DB::table('igrejas')->insert([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $this->orgA,
        'regional_id' => $regionalDeB,
        'nome' => 'Igreja A',
        'created_at' => now(),
        'updated_at' => now(),
    ]));
})->throws(QueryException::class, 'foreign key');
