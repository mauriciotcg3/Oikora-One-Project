<?php

use Illuminate\Support\Facades\DB;

// Lista fechada do plan.md §2: nenhuma tabela fora dela pode ficar sem `organizacao_id`.
const TABELAS_FORA_DA_RLS = [
    'organizacoes', 'contas', 'sessions', 'personal_access_tokens', 'codigos_verificacao',
    'eventos_autenticacao', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks',
    'auditoria_plataforma', 'migrations',
];

/** @return list<string> */
function tabelasComOrganizacao(): array
{
    return collect(DB::connection('pgsql_dono')->select(<<<'SQL'
        select c.table_name from information_schema.columns c
        join information_schema.tables t using (table_schema, table_name)
        where c.table_schema = 'public' and c.column_name = 'organizacao_id' and t.table_type = 'BASE TABLE'
    SQL))->pluck('table_name')->all();
}

it('exige RLS habilitada, forçada e com política em toda tabela com organizacao_id', function (): void {
    $tabelas = tabelasComOrganizacao();

    expect($tabelas)->toContain('acessos');

    foreach ($tabelas as $tabela) {
        $estado = DB::connection('pgsql_dono')->selectOne(
            'select relrowsecurity, relforcerowsecurity from pg_class where oid = ?::regclass',
            [$tabela],
        );
        $politicas = DB::connection('pgsql_dono')->select(
            "select qual, with_check from pg_policies where schemaname = 'public' and tablename = ?",
            [$tabela],
        );

        expect($estado->relrowsecurity)->toBeTrue("{$tabela} sem RLS")
            ->and($estado->relforcerowsecurity)->toBeTrue("{$tabela} sem FORCE")
            ->and($politicas)->not->toBeEmpty("{$tabela} sem política")
            ->and($politicas[0]->qual)->toContain('app_organizacao_atual')
            ->and($politicas[0]->with_check)->toContain('app_organizacao_atual');
    }
});

it('só deixa sem organizacao_id as tabelas da lista fechada', function (): void {
    $todas = collect(DB::connection('pgsql_dono')->select("select tablename from pg_tables where schemaname = 'public'"))
        ->pluck('tablename');

    expect($todas->diff(tabelasComOrganizacao())->diff(TABELAS_FORA_DA_RLS)->values()->all())->toBe([]);
});

it('mantém oikora_app sem BYPASSRLS e sem ser dono de tabela', function (): void {
    $papel = DB::selectOne("select rolbypassrls, rolsuper from pg_roles where rolname = 'oikora_app'");
    $tabelasDoApp = DB::scalar("select count(*) from pg_tables where schemaname = 'public' and tableowner = 'oikora_app'");

    expect(DB::scalar('select current_user'))->toBe('oikora_app')
        ->and($papel->rolbypassrls)->toBeFalse()
        ->and($papel->rolsuper)->toBeFalse()
        ->and($tabelasDoApp)->toBe(0);
});
