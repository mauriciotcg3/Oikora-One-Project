<?php

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

/** Pre-Review Gate (OIK-ADR-0010 §7, §8; U-14): as peças que o `composer gate` e o CI usam. */
function deptrac(string $config): int
{
    return Process::path(base_path())
        ->timeout(120)
        ->run(['vendor/bin/deptrac', 'analyse', '--no-progress', '--config-file='.$config, '--cache-file='.sys_get_temp_dir().'/deptrac-'.md5($config).'.cache'])
        ->exitCode() ?? 1;
}

it('aprova o código atual no Deptrac', function (): void {
    expect(deptrac(base_path('deptrac.yaml')))->toBe(0);
});

it('reprova no Deptrac um Domain que importa o framework', function (): void {
    $fixture = base_path('tests/Fixtures/Deptrac/violacao');
    $config = (string) file_get_contents(base_path('deptrac.yaml'));
    $config = preg_replace('#paths:\s*\n\s*- \./app#', "paths:\n    - {$fixture}", $config, 1, $trocas);
    expect($trocas)->toBe(1);

    $arquivo = sys_get_temp_dir().'/deptrac-violacao.yaml';
    file_put_contents($arquivo, $config);

    expect(deptrac($arquivo))->not->toBe(0);
});

it('mantém o PHPStan no nível 8 e sem baseline', function (): void {
    $neon = (string) file_get_contents(base_path('phpstan.neon'));

    expect($neon)->toMatch('/^\s*level:\s*8\s*$/m')
        ->and($neon)->not->toContain('baseline')
        ->and(file_exists(base_path('phpstan-baseline.neon')))->toBeFalse();
});

it('limita o JS inicial do painel a 350 KB gzip', function (): void {
    $arquivo = dirname(base_path()).'/web/.size-limit.json';
    if (! file_exists($arquivo)) {
        // No container da API só `api/` está montado; o CI roda com o repositório inteiro.
        $this->markTestSkipped('web/.size-limit.json fora do alcance deste ambiente.');
    }

    $limites = json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR);
    $painel = collect($limites)->firstWhere('name', 'painel');

    expect($painel)->not->toBeNull()
        ->and($painel['limit'])->toBe('350 KB')
        ->and($painel['gzip'] ?? true)->toBeTrue();
});
