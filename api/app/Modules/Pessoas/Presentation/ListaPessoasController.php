<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Consultas\FiltroPessoas;
use App\Modules\Pessoas\Application\Consultas\ListarPessoas;
use App\Modules\Pessoas\Domain\Situacao;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /painel/pessoas` (plan.md §7; T-30): `pagina`, `por_pagina` (25, 50 ou 100), `ordem` e os
 * filtros da U-17 — `igreja`, `vinculo`, `situacao`, `tipo_membro`, `funcao` (uuids), `ficha_incompleta`
 * e `aniversariantes_mes` (1–12; o front manda o mês corrente no fuso da organização).
 */
final class ListaPessoasController
{
    public function __invoke(Request $request, ListarPessoas $listar): JsonResponse
    {
        $dados = $request->validate([
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'por_pagina' => ['sometimes', 'integer', Rule::in([25, 50, 100])],
            'ordem' => ['sometimes', Rule::in(FiltroPessoas::ORDENS)],
            'igreja' => ['sometimes', 'uuid'],
            'vinculo' => ['sometimes', Rule::enum(Vinculo::class)],
            'situacao' => ['sometimes', Rule::enum(Situacao::class)],
            'tipo_membro' => ['sometimes', 'uuid'],
            'funcao' => ['sometimes', 'uuid'],
            'ficha_incompleta' => ['sometimes', 'boolean'],
            'aniversariantes_mes' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        if (! $listar->permitido()) {
            Log::info('authz.denied', ['permissao' => Permissao::PessoasVer->value]);

            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        $filtros = array_intersect_key($dados, array_flip(['igreja', 'vinculo', 'situacao', 'tipo_membro', 'funcao']));
        if ($request->boolean('ficha_incompleta')) {
            $filtros['ficha_incompleta'] = true;
        }
        if (isset($dados['aniversariantes_mes'])) {
            $filtros['aniversariantes_mes'] = (int) $dados['aniversariantes_mes'];
        }

        $pagina = (int) ($dados['pagina'] ?? 1);
        $porPagina = (int) ($dados['por_pagina'] ?? 25);
        $resultado = $listar->executar($filtros, $pagina, $porPagina, (string) ($dados['ordem'] ?? 'nome'));

        return new JsonResponse([
            'data' => $resultado['itens'],
            'meta' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $resultado['total'],
                'criterio' => $resultado['criterio'],
            ],
        ]);
    }
}
