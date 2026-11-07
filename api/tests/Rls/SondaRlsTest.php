<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $esquema = Schema::connection('pgsql_dono');
    $esquema->dropIfExists('rls_sonda_filha');
    $esquema->dropIfExists('rls_sonda');

    $esquema->create('rls_sonda', function (Blueprint $tabela): void {
        $tabela->id()->generatedAs()->always();
        $tabela->organizacao();
        $tabela->text('valor');
    });
    $esquema->create('rls_sonda_filha', function (Blueprint $tabela): void {
        $tabela->id()->generatedAs()->always();
        $tabela->organizacao();
        $tabela->foreignComposta('rls_sonda_id', 'rls_sonda');
    });

    $this->orgA = criarOrganizacao('A');
    $this->orgB = criarOrganizacao('B');
    $this->linhaB = noContexto($this->orgB, null, fn (): int => (int) DB::table('rls_sonda')
        ->insertGetId(['organizacao_id' => $this->orgB, 'valor' => 'de B']));
});

afterEach(function (): void {
    $esquema = Schema::connection('pgsql_dono');
    $esquema->dropIfExists('rls_sonda_filha');
    $esquema->dropIfExists('rls_sonda');
});

it('impede a organização A de ler, alterar e excluir linhas da B', function (): void {
    noContexto($this->orgA, null, function (): void {
        expect(DB::table('rls_sonda')->count())->toBe(0)
            ->and(DB::table('rls_sonda')->where('id', $this->linhaB)->update(['valor' => 'invadido']))->toBe(0)
            ->and(DB::table('rls_sonda')->where('id', $this->linhaB)->delete())->toBe(0);
    });

    noContexto($this->orgB, null, fn () => expect(DB::table('rls_sonda')->value('valor'))->toBe('de B'));
});

it('impede a organização A de inserir linha em nome da B', function (): void {
    noContexto($this->orgA, null, fn () => DB::table('rls_sonda')
        ->insert(['organizacao_id' => $this->orgB, 'valor' => 'invadido']));
})->throws(QueryException::class, 'row-level security');

it('lança tenant.context.missing em vez de devolver lista vazia', function (): void {
    DB::table('rls_sonda')->count();
})->throws(QueryException::class, 'tenant.context.missing');

it('não herda o contexto depois que a transação termina', function (): void {
    noContexto($this->orgB, null, fn () => DB::table('rls_sonda')->count());

    DB::table('rls_sonda')->count();
})->throws(QueryException::class, 'tenant.context.missing');

it('recusa FK composta para linha de outra organização', function (): void {
    noContexto($this->orgA, null, fn () => DB::table('rls_sonda_filha')
        ->insert(['organizacao_id' => $this->orgA, 'rls_sonda_id' => $this->linhaB]));
})->throws(QueryException::class, 'foreign key');
