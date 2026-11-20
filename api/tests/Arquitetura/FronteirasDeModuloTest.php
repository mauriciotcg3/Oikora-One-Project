<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * MOD-002 e DB-001 (OIK-ADR-0010 §1): um módulo só usa outro pelo `Contracts/` dele ou pela linguagem
 * publicada que todas as Actions precisam (permissão, alcance declarado e auditoria).
 */
const LINGUAGEM_PUBLICADA = [
    'App\\Modules\\Autorizacao\\Domain\\Permissao',
    'App\\Modules\\Autorizacao\\Domain\\EscopoTipo',
    'App\\Modules\\Autorizacao\\Application\\RequerPermissao',
    'App\\Modules\\Auditoria\\Application\\Auditoria',
    'App\\Modules\\Auditoria\\Application\\AuditaCom',
    'App\\Modules\\Auditoria\\Domain\\EventoAuditoria',
];

it('só cruza módulos pelo Contracts ou pela linguagem publicada', function (): void {
    $violacoes = [];

    foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) ?: [] as $pasta) {
        $modulo = basename($pasta);
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/App\\\\Modules\\\\(\w+)\\\\([\w\\\\]+)/', (string) file_get_contents($arquivo->getPathname()), $usos, PREG_SET_ORDER);

            foreach ($usos as [$classe, $outro, $resto]) {
                $permitido = $outro === $modulo
                    || str_starts_with($resto, 'Contracts\\')
                    || in_array($classe, LINGUAGEM_PUBLICADA, true);

                if (! $permitido) {
                    $violacoes[] = "{$modulo}: ".basename($arquivo->getPathname())." usa {$classe}";
                }
            }
        }
    }

    expect(array_values(array_unique($violacoes)))->toBe([]);
});
