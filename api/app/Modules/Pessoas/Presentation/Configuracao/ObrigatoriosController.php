<?php

namespace App\Modules\Pessoas\Presentation\Configuracao;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\CamposObrigatorios;
use App\Modules\Pessoas\Application\Configuracao\DefinirObrigatoriosAction;
use App\Modules\Pessoas\Domain\CampoFicha;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** `GET|PUT /painel/configuracao/obrigatorios` (plan.md §7; RN-13; U-06). */
final class ObrigatoriosController
{
    public function mostrar(AlcanceAtual $alcance, CamposObrigatorios $obrigatorios): JsonResponse
    {
        if (! $alcance->temNaOrganizacao(Permissao::PessoasConfigurar)) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['data' => $obrigatorios->todos()]);
    }

    public function definir(Request $request, DefinirObrigatoriosAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'vinculo' => ['required', Rule::enum(Vinculo::class)],
            'campos' => ['present', 'array'],
            'campos.*' => ['required', 'string', 'distinct', Rule::enum(CampoFicha::class)],
        ]);

        /** @var list<string> $campos */
        $campos = array_values($dados['campos']);
        $resultado = $acao->executar(Vinculo::from($dados['vinculo']), $campos);

        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
        }

        return new JsonResponse(['data' => $resultado->dados['obrigatorios']]);
    }
}
