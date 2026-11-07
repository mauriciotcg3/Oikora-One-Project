<?php

use App\Modules\Organizacao\Domain\EstadoOrganizacao;
use App\Modules\Organizacao\Domain\ModoMatricula;
use App\Modules\Organizacao\Infrastructure\Igreja;
use App\Modules\Organizacao\Infrastructure\Organizacao;
use App\Modules\Organizacao\Infrastructure\Regional;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

it('cria organização com os defaults de operação', function (): void {
    $id = DB::connection('pgsql_dono')->table('organizacoes')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'nome' => 'Organização padrão',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organizacao = DB::connection('pgsql_dono')->table('organizacoes')->find($id);

    expect($organizacao->estado)->toBe('ativa')
        ->and($organizacao->fuso)->toBe('America/Sao_Paulo')
        ->and($organizacao->modo_matricula)->toBe('manual')
        ->and($organizacao->limite_whatsapp_mensal)->toBeNull();
});

it('restringe os estados possíveis da organização', function (string $estado): void {
    DB::connection('pgsql_dono')->table('organizacoes')->insert([
        'uuid' => (string) Str::uuid(),
        'nome' => "Organização {$estado}",
        'estado' => $estado,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::connection('pgsql_dono')->table('organizacoes')->where('estado', $estado)->exists())->toBeTrue();
})->with(['ativa', 'em_migracao']);

it('recusa estado de organização desconhecido', function (): void {
    DB::connection('pgsql_dono')->table('organizacoes')->insert([
        'uuid' => (string) Str::uuid(),
        'nome' => 'Organização inválida',
        'estado' => 'suspensa',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class, 'organizacoes_estado_valido');

it('recusa limite mensal de WhatsApp negativo', function (): void {
    DB::connection('pgsql_dono')->table('organizacoes')->insert([
        'uuid' => (string) Str::uuid(),
        'nome' => 'Organização com limite inválido',
        'limite_whatsapp_mensal' => -1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class, 'organizacoes_limite_whatsapp_mensal_valido');

it('expõe somente os valores aprovados para estado e modo de matrícula', function (): void {
    expect(array_column(EstadoOrganizacao::cases(), 'value'))->toBe(['ativa', 'em_migracao'])
        ->and(array_column(ModoMatricula::cases(), 'value'))->toBe(['manual', 'automatica']);
});

it('mapeia os modelos para as tabelas do módulo', function (): void {
    expect((new Organizacao)->getTable())->toBe('organizacoes')
        ->and((new Regional)->getTable())->toBe('regionais')
        ->and((new Igreja)->getTable())->toBe('igrejas');
});
