<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\MinhaConta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MeController
{
    /**
     * Conta, organização ativa, organizações com acesso, capacidades do alcance, elevação e 2FA.
     */
    public function mostrar(Request $request, MinhaConta $minhaConta): JsonResponse
    {
        return response()->json(['data' => $minhaConta->consultar($this->contaId($request), $request->session())]);
    }

    /** Troca a organização ativa. Só vale organização com acesso ativo da conta (OIK-ADR-0003 §12). */
    public function escolherOrganizacao(Request $request, MinhaConta $minhaConta): Response|JsonResponse
    {
        $dados = $request->validate(['organizacao' => ['required', 'uuid']]);

        if (! $minhaConta->escolherOrganizacao($this->contaId($request), $dados['organizacao'], $request->session())) {
            return response()->json(['codigo' => 'organizacao_sem_acesso'], 403);
        }

        return response()->noContent();
    }

    private function contaId(Request $request): int
    {
        return (int) $request->user()?->getAuthIdentifier();
    }
}
