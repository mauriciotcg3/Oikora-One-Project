<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Application\Actions\ExcluirPessoaAction;
use App\Modules\Pessoas\Application\Actions\RecuperarPessoaAction;
use App\Modules\Pessoas\Application\Lixeira\Lixeira;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * `DELETE /painel/pessoas/{id}` (motivo), `GET /painel/lixeira` e `POST /painel/lixeira/{id}/recuperar`
 * (plan.md §7; T-35). A confirmação é do front; o servidor exige `pessoas.excluir` na igreja da pessoa.
 */
final class LixeiraController
{
    public function excluir(Request $request, string $pessoa, ExcluirPessoaAction $acao): JsonResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:500']]);

        return DuplicadosController::responder($acao->executar($pessoa, trim($dados['motivo'])));
    }

    public function listar(Request $request, AlcanceAtual $alcance, Lixeira $lixeira, CatalogoIgrejas $igrejas): JsonResponse
    {
        $dados = $request->validate([
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'por_pagina' => ['sometimes', 'integer', Rule::in([25, 50, 100])],
        ]);
        $comExcluir = $alcance->igrejasCom(Permissao::PessoasExcluir);
        if ($comExcluir === []) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        $pagina = (int) ($dados['pagina'] ?? 1);
        $porPagina = (int) ($dados['por_pagina'] ?? 25);
        $resultado = $lixeira->listar($comExcluir, $pagina, $porPagina);
        $resumo = $igrejas->resumo(array_values(array_unique(array_column($resultado['itens'], 'igreja_id'))));
        $dias = (int) config('lgpd.dias_lixeira', 30);

        return new JsonResponse([
            'data' => array_map(static function (array $item) use ($resumo, $dias): array {
                $excluida = new DateTimeImmutable($item['excluida_em']);

                return [
                    'uuid' => $item['uuid'],
                    'nome' => $item['nome'],
                    'igreja' => $resumo[$item['igreja_id']] ?? null,
                    'motivo' => $item['motivo'],
                    'excluida_em' => $excluida->format(DATE_ATOM),
                    'recuperavel_ate' => $excluida->modify("+{$dias} days")->format(DATE_ATOM),
                    'meta' => ['acoes' => $excluida > new DateTimeImmutable("-{$dias} days") ? ['recuperar'] : []],
                ];
            }, $resultado['itens']),
            'meta' => ['pagina' => $pagina, 'por_pagina' => $porPagina, 'total' => $resultado['total']],
        ]);
    }

    public function recuperar(string $pessoa, RecuperarPessoaAction $acao): JsonResponse
    {
        return DuplicadosController::responder($acao->executar($pessoa));
    }
}
