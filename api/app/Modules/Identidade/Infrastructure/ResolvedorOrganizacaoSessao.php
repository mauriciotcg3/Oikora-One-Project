<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\AcessosDaConta;
use App\Modules\Identidade\Application\SessaoDoPainel;
use App\Shared\Tenancy\Contracts\ResolvedorOrganizacaoAtiva;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organização ativa guardada na sessão e revalidada a cada requisição contra os acessos ativos da conta
 * (OIK-ADR-0003 §12; CA-09). Com um único acesso, a escolha é automática. Nada vem de parâmetro livre.
 */
final readonly class ResolvedorOrganizacaoSessao implements ResolvedorOrganizacaoAtiva
{
    public function __construct(private AcessosDaConta $acessos) {}

    public function resolver(Request $request): int
    {
        $conta = $request->user();
        if ($conta === null) {
            throw $this->recusar('authz.context_missing');
        }

        $contaId = (int) $conta->getAuthIdentifier();
        $sessao = $request->session();
        $ativas = array_map(static fn ($o): int => $o->id, $this->acessos->organizacoes($contaId));
        $guardada = SessaoDoPainel::organizacao($sessao);

        if ($guardada !== null) {
            if (in_array($guardada, $ativas, true)) {
                return $guardada;
            }

            // Acesso revogado depois do login: a requisição não grava contexto (CA-09).
            SessaoDoPainel::esquecerOrganizacao($sessao);
            Log::info('identidade.organizacao_ativa.revogada', ['module' => 'Identidade', 'conta_id' => $contaId, 'status' => 'negado']);

            throw $this->recusar('acesso_revogado');
        }

        if (count($ativas) === 1) {
            SessaoDoPainel::escolherOrganizacao($sessao, $ativas[0]);

            return $ativas[0];
        }

        throw $this->recusar($ativas === [] ? 'acesso_inexistente' : 'organizacao_nao_escolhida');
    }

    private function recusar(string $codigo): HttpResponseException
    {
        return new HttpResponseException(new JsonResponse(['codigo' => $codigo], Response::HTTP_FORBIDDEN));
    }
}
