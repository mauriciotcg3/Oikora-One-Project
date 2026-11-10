<?php

namespace App\Shared\Observabilidade\Log;

/** Deriva `module` e `action` do nome da classe que executa (controller, Action ou job). */
final class Origem
{
    /** @return array{0: string|null, 1: string|null} */
    public static function daAcao(string $acao): array
    {
        $partesDaAcao = explode('@', $acao, 2);
        $classe = $partesDaAcao[0];
        $metodo = $partesDaAcao[1] ?? null;

        if (preg_match('/^App\\\\(?:Modules|Shared)\\\\([A-Za-z]+)\\\\/', $classe, $partes) !== 1) {
            return [null, $classe === 'Closure' ? null : $classe];
        }

        $nome = class_basename($classe);
        if ($metodo !== null && $metodo !== '__invoke') {
            $nome .= '@'.$metodo;
        }

        return [$partes[1], $nome];
    }
}
