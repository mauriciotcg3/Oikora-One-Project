<?php

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Autorizacao\Application\RequerPermissao;
use Tests\TestCase;

uses(TestCase::class);

it('exige permissão e evento de auditoria em toda Action de escrita', function (): void {
    // Actions ficam em `Application/Actions/` ou numa pasta de assunto (`Application/Configuracao/`).
    $arquivos = glob(app_path('Modules/*/Application/*/*Action.php')) ?: [];
    expect($arquivos)->toContain(app_path('Modules/Pessoas/Application/Configuracao/CriarItemListaAction.php'));

    foreach ($arquivos as $arquivo) {
        $conteudo = file_get_contents($arquivo);
        preg_match('/namespace\s+([^;]+);/', $conteudo, $namespace);
        preg_match('/(?:final\s+)?class\s+(\w+Action)/', $conteudo, $classe);

        if (! isset($namespace[1], $classe[1])) {
            continue;
        }

        $reflexao = new ReflectionClass($namespace[1].'\\'.$classe[1]);
        expect($reflexao->getAttributes(RequerPermissao::class))->not->toBeEmpty("{$reflexao->getName()} sem #[RequerPermissao]")
            ->and($reflexao->getAttributes(AuditaCom::class))->not->toBeEmpty("{$reflexao->getName()} sem #[AuditaCom]");
    }

    expect($arquivos)->toBeArray();
});
