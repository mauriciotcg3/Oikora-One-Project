<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('cria antecipadamente a partição mensal solicitada', function (): void {
    $mes = Carbon::now('UTC')->startOfMonth()->addMonths(2);
    $nome = 'auditoria_'.$mes->format('Ym');

    $this->artisan('auditoria:criar-particoes', ['--meses' => 3])->assertSuccessful();

    $particao = DB::connection('pgsql_dono')->selectOne(
        'select to_regclass(?) as nome',
        ['public.'.$nome],
    );

    expect($particao->nome)->toBe($nome);
});

it('remove partição mensal anterior à retenção', function (): void {
    $inicio = Carbon::now('UTC')->startOfMonth()->subYears(6);
    $fim = $inicio->copy()->addMonth();
    $nome = 'auditoria_'.$inicio->format('Ym');
    $limiteInferior = $inicio->format('Y-m-d H:i:sP');
    $limiteSuperior = $fim->format('Y-m-d H:i:sP');
    $dono = DB::connection('pgsql_dono');
    $dono->statement(
        "create table {$nome} partition of auditoria "
        ."for values from ('{$limiteInferior}') to ('{$limiteSuperior}')",
    );

    $this->artisan('auditoria:aplicar-retencao', ['--anos' => 5])->assertSuccessful();

    $particao = $dono->selectOne('select to_regclass(?) as nome', ['public.'.$nome]);

    expect($particao->nome)->toBeNull();
});
