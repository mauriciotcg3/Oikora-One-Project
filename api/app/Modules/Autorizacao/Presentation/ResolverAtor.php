<?php

namespace App\Modules\Autorizacao\Presentation;

use App\Modules\Autorizacao\Application\AlcanceDaRequisicao;
use App\Modules\Autorizacao\Contracts\Ator;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Depois do contexto de organização: localiza o acesso ativo da conta, conferido a cada requisição
 * (OIK-ADR-0003 §11), e define o ator do alcance. Sem acesso ativo, 403.
 */
final readonly class ResolverAtor
{
    public function __construct(
        private AcessosDaOrganizacao $acessos,
        private AlcanceDaRequisicao $alcance,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organizacaoId = $request->attributes->get('organizacao_id');
        $conta = $request->user();

        if (! is_int($organizacaoId) || $conta === null) {
            return new JsonResponse(['codigo' => 'authz.context_missing'], Response::HTTP_FORBIDDEN);
        }

        $contaId = (int) $conta->getAuthIdentifier();
        $acessoId = $this->acessos->ativoDaConta($contaId);

        if ($acessoId === null) {
            return new JsonResponse(['codigo' => 'acesso_inexistente'], Response::HTTP_FORBIDDEN);
        }

        $this->alcance->definir(new Ator($organizacaoId, $contaId, $acessoId));

        try {
            return $next($request);
        } finally {
            $this->alcance->limpar();
        }
    }
}
