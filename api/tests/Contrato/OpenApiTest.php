<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Contrato\FichaProjetadaController;
use Tests\TestCase;

uses(TestCase::class);

/** OIK-SPEC-0001 T-05 — contrato OpenAPI gerado pelo Scramble (OIK-ADR-0006 §8; OIK-ADR-0010 §4; CA-29). */
function exportarContrato(): array
{
    // Arquivo, não `--stdout`: os diagnósticos do Scramble saem no mesmo buffer do Artisan.
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'openapi');

    try {
        expect(Artisan::call('scramble:export', ['--path' => $arquivo]))->toBe(0);

        return json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        @unlink($arquivo);
    }
}

/** Resolve um `$ref` local (`#/components/schemas/...`) ou devolve o próprio schema. */
function resolverSchema(array $documento, array $schema): array
{
    if (! isset($schema['$ref'])) {
        return $schema;
    }

    $caminho = explode('/', substr($schema['$ref'], 2));

    return data_get($documento, implode('.', $caminho));
}

it('exporta o mesmo contrato que está versionado em api/openapi.json', function (): void {
    $versionado = json_decode((string) file_get_contents(base_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);

    expect(exportarContrato())->toBe($versionado, 'Contrato desatualizado: rode `php artisan scramble:export` e versione api/openapi.json.');
});

it('documenta GET /saude com o schema da resposta, num servidor relativo', function (): void {
    $documento = exportarContrato();
    $schema = $documento['paths']['/saude']['get']['responses']['200']['content']['application/json']['schema'];

    expect($documento['servers'])->toBe([['url' => '/api/v1']])
        ->and($schema['required'])->toBe(['vivo', 'banco', 'versao', 'filas'])
        ->and($schema['properties']['banco']['enum'])->toBe(['ok', 'indisponivel'])
        ->and($schema['properties']['filas'])->toBe(['type' => 'object', 'additionalProperties' => ['type' => 'integer']]);
});

it('só documenta a /api/v1 e não expõe a interface do Scramble', function (): void {
    expect(array_keys(exportarContrato()['paths']))->each->not->toStartWith('/api/')
        ->and(collect(Route::getRoutes()->getRoutes())->map->uri()->filter(fn (string $uri): bool => str_starts_with($uri, 'docs/'))->all())->toBe([]);
});

it('marca o campo de projeção como opcional no schema do Resource', function (): void {
    Route::get('api/v1/_fixture/ficha-projetada', FichaProjetadaController::class);

    $documento = exportarContrato();
    $resposta = $documento['paths']['/_fixture/ficha-projetada']['get']['responses']['200']['content']['application/json']['schema'];
    $ficha = resolverSchema($documento, resolverSchema($documento, $resposta)['properties']['data'] ?? $resposta);

    expect(array_keys($ficha['properties']))->toBe(['uuid', 'nome', 'cpf'])
        ->and($ficha['required'])->toContain('uuid', 'nome')
        ->and($ficha['required'])->not->toContain('cpf');
});
