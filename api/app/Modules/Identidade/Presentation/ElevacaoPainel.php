<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\SessaoDoPainel;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas `/api/v1/painel/*` exigem elevação viva (OIK-ADR-0003 §9): 2h sem uso ou 12h no total.
 * Expirada, 403 `elevacao_necessaria`; o front abre o diálogo e repete o pedido (OIK-ADR-0006 §10).
 */
final class ElevacaoPainel
{
    public const ALIAS = 'elevacao.painel';

    public function handle(Request $request, Closure $next): Response
    {
        if (! SessaoDoPainel::usarElevacao($request->session())) {
            return new JsonResponse(
                ['codigo' => 'elevacao_necessaria', 'message' => 'Confirme sua senha para continuar no painel.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
