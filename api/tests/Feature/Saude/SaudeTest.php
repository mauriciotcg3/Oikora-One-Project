<?php

use Illuminate\Support\Facades\DB;

it('responde sem autenticação com vivo, banco, versão e filas', function (): void {
    $this->getJson('/api/v1/saude')
        ->assertOk()
        ->assertJsonPath('vivo', true)
        ->assertJsonPath('banco', 'ok')
        ->assertJsonPath('filas.codigos', 0)
        ->assertJsonPath('filas.default', 0)
        ->assertJsonStructure(['vivo', 'banco', 'versao', 'filas']);
});

it('mostra a idade do job mais antigo de cada fila', function (): void {
    DB::connection('pgsql_dono')->table('jobs')->insert([
        'queue' => 'codigos',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => now()->subSeconds(120)->getTimestamp(),
        'created_at' => now()->subSeconds(120)->getTimestamp(),
    ]);

    expect($this->getJson('/api/v1/saude')->json('filas.codigos'))->toBeGreaterThanOrEqual(120);
});

it('não expõe dado de organização nem de pessoa', function (): void {
    criarOrganizacao('Igreja Secreta');
    criarConta(['email' => 'secreta@exemplo.test']);

    $resposta = $this->getJson('/api/v1/saude');

    expect(array_keys($resposta->json()))->toEqualCanonicalizing(['vivo', 'banco', 'versao', 'filas'])
        ->and($resposta->getContent())->not->toContain('Secreta')->not->toContain('secreta@');
});

it('usa PostgreSQL 17 com pg_trgm e unaccent', function (): void {
    $versao = (int) DB::scalar("select current_setting('server_version_num')");
    $extensoes = collect(DB::select('select extname from pg_extension'))->pluck('extname');

    expect(intdiv($versao, 10000))->toBe(17)
        ->and($extensoes)->toContain('pg_trgm')->toContain('unaccent');
});
