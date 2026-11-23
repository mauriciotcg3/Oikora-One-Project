<?php

namespace App\Modules\Avisos\Presentation;

use App\Modules\Avisos\Application\CaixaDeAvisos;
use App\Modules\Avisos\Application\TextosDaCaixa;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as Status;

/**
 * Caixa de avisos (OIK-ADR-0008 §7; plan.md §7): `GET /avisos` (paginada, mais recente primeiro),
 * `GET /avisos/nao-lidos`, `POST /avisos/{id}/lido` e `POST /avisos/lidos`. Sessão e organização ativa;
 * a conta só vê e marca os próprios avisos. O texto é montado aqui, na leitura.
 */
final class CaixaAvisosController
{
    public function listar(Request $request, CaixaDeAvisos $caixa, ConsultaOrganizacoes $organizacoes): JsonResponse
    {
        $dados = $request->validate([
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'por_pagina' => ['sometimes', 'integer', Rule::in([25, 50, 100])],
            'nao_lidos' => ['sometimes', 'boolean'],
        ]);

        $pagina = (int) ($dados['pagina'] ?? 1);
        $porPagina = (int) ($dados['por_pagina'] ?? 25);
        $contaId = $this->conta($request);
        $resultado = $caixa->listar($contaId, $pagina, $porPagina, (bool) ($dados['nao_lidos'] ?? false));
        $organizacao = $organizacoes->perfilDeEnvio((int) $request->attributes->get('organizacao_id'))->nome;

        return new JsonResponse([
            'data' => array_map(static function (array $aviso) use ($organizacao): array {
                $texto = TextosDaCaixa::do($aviso['tipo'], $organizacao);

                return [
                    'id' => $aviso['uuid'],
                    'tipo' => $aviso['tipo']->value,
                    'titulo' => $texto['titulo'],
                    'texto' => $texto['texto'],
                    'acao' => $texto['acao'],
                    'destino' => $aviso['destino'],
                    'lido_em' => $aviso['lido_em'] === null ? null : (new DateTimeImmutable($aviso['lido_em']))->format(DATE_ATOM),
                    'criado_em' => (new DateTimeImmutable($aviso['criado_em']))->format(DATE_ATOM),
                ];
            }, $resultado['itens']),
            'meta' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $resultado['total'],
                'nao_lidos' => $caixa->naoLidos($contaId),
            ],
        ]);
    }

    public function naoLidos(Request $request, CaixaDeAvisos $caixa): JsonResponse
    {
        return new JsonResponse(['nao_lidos' => $caixa->naoLidos($this->conta($request))]);
    }

    public function marcarLido(Request $request, CaixaDeAvisos $caixa, string $aviso): Response|JsonResponse
    {
        if (! $caixa->marcarLido($this->conta($request), $aviso)) {
            return new JsonResponse(['codigo' => 'aviso_nao_encontrado'], Status::HTTP_NOT_FOUND);
        }

        return response()->noContent();
    }

    public function marcarTodos(Request $request, CaixaDeAvisos $caixa): JsonResponse
    {
        $marcados = $caixa->marcarTodos($this->conta($request));

        return new JsonResponse(['marcados' => $marcados, 'nao_lidos' => 0]);
    }

    private function conta(Request $request): int
    {
        return (int) $request->user()?->getAuthIdentifier();
    }
}
