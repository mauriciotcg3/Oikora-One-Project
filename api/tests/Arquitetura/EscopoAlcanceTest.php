<?php

use App\Modules\Pessoas\Infrastructure\Escopos\PessoaEscopoAlcance;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use Tests\TestCase;

uses(TestCase::class);

/** @return list<string> */
function arquivosPhpDoApp(): array
{
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));
    $arquivos = [];
    foreach ($iterador as $arquivo) {
        if ($arquivo->getExtension() === 'php') {
            $arquivos[] = $arquivo->getPathname();
        }
    }

    return $arquivos;
}

it('liga o PessoaEscopoAlcance no model Pessoa', function (): void {
    new Pessoa; // o escopo é registrado no boot do model

    expect(Pessoa::hasGlobalScope(PessoaEscopoAlcance::class))->toBeTrue();
});

it('só a BuscaDenominacao remove o PessoaEscopoAlcance', function (): void {
    $removem = [];
    foreach (arquivosPhpDoApp() as $arquivo) {
        $codigo = (string) file_get_contents($arquivo);
        if (preg_match('/withoutGlobalScopes\s*\(|withoutGlobalScope\s*\(\s*PessoaEscopoAlcance/', $codigo) === 1) {
            $removem[] = basename($arquivo);
        }
    }

    expect($removem)->toBe(['BuscaDenominacaoPostgres.php']);
});

it('não consulta a tabela pessoas por fora do model, a não ser nas migrations', function (): void {
    $porFora = [];
    foreach (arquivosPhpDoApp() as $arquivo) {
        if (str_contains($arquivo, DIRECTORY_SEPARATOR.'Migrations'.DIRECTORY_SEPARATOR)) {
            continue;
        }
        if (preg_match("/table\\(\\s*'pessoas'|from\\s+\"?pessoas\\b/i", (string) file_get_contents($arquivo)) === 1) {
            $porFora[] = basename($arquivo);
        }
    }

    expect($porFora)->toBe([]);
});
