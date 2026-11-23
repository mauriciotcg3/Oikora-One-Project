<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Pessoas\Application\Actions\MarcarNaoDuplicadasAction;
use App\Modules\Pessoas\Application\Actions\MesclarPessoasAction;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\ListarDuplicados;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /painel/duplicados`, `POST /painel/duplicados/nao-duplicados` e `POST /painel/mesclagens`
 * (plan.md §7; T-32). Na mesclagem, `valores` diz, por campo divergente, de qual ficha vem o valor
 * (`mantida` ou `absorvida`); campo não citado fica com o da mantida.
 */
final class DuplicadosController
{
    public function listar(Request $request, ListarDuplicados $listar): JsonResponse
    {
        $dados = $request->validate([
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'por_pagina' => ['sometimes', 'integer', Rule::in([25, 50, 100])],
        ]);
        if (! $listar->permitido()) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        $pagina = (int) ($dados['pagina'] ?? 1);
        $porPagina = (int) ($dados['por_pagina'] ?? 25);
        $resultado = $listar->executar($pagina, $porPagina);

        return new JsonResponse([
            'data' => $resultado['itens'],
            'meta' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $resultado['total'],
                'criterio' => 'Pares com nome completo e nascimento, CPF ou e-mail iguais (RN-23), fora da lixeira e sem os marcados como pessoas diferentes',
            ],
        ]);
    }

    public function naoDuplicadas(Request $request, MarcarNaoDuplicadasAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'pessoa' => ['required', 'uuid'],
            'outra' => ['required', 'uuid'],
        ]);

        return self::responder($acao->executar($dados['pessoa'], $dados['outra']));
    }

    public function mesclar(Request $request, MesclarPessoasAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'mantida' => ['required', 'uuid'],
            'absorvida' => ['required', 'uuid'],
            'valores' => ['sometimes', 'array'],
            'valores.*' => ['string', Rule::in(['mantida', 'absorvida'])],
        ]);

        /** @var array<string, string> $valores */
        $valores = $dados['valores'] ?? [];

        return self::responder($acao->executar($dados['mantida'], $dados['absorvida'], $valores));
    }

    public static function responder(Resultado $resultado): JsonResponse
    {
        if ($resultado->status === 422 && isset($resultado->dados['erros'])) {
            /** @var array<string, string> $erros */
            $erros = $resultado->dados['erros'];

            throw ValidationException::withMessages(array_map(static fn (string $erro): array => [$erro], $erros));
        }
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo, ...$resultado->dados], $resultado->status);
        }
        if ($resultado->status === 204) {
            return new JsonResponse(null, 204);
        }

        return new JsonResponse($resultado->dados === [] ? null : $resultado->dados, $resultado->status);
    }
}
