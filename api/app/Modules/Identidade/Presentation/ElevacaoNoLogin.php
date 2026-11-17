<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\AcessosDaConta;
use App\Modules\Identidade\Application\ExigenciaDeDoisFatores;
use App\Modules\Identidade\Application\RegistrarElevacao;
use App\Modules\Identidade\Application\SessaoDoPainel;
use Illuminate\Http\Request;

/**
 * Login pela entrada `painel` já abre a elevação (U-32): senha mais o 2FA quando a conta o tem. Quem é
 * obrigado a ter 2FA e não cadastrou entra só na área da conta e recebe `dois_fatores_cadastro_necessario`
 * ao tentar elevar.
 */
final readonly class ElevacaoNoLogin
{
    public const ENTRADA_PAINEL = 'painel';

    public function __construct(
        private ExigenciaDeDoisFatores $exigencia,
        private AcessosDaConta $acessos,
        private RegistrarElevacao $registrar,
    ) {}

    /** @param string|null $segundoFator canal conferido no login; null quando a conta não tem 2FA */
    public function abrir(Request $request, int $contaId, ?string $entrada, ?string $segundoFator): void
    {
        if ($entrada !== self::ENTRADA_PAINEL) {
            return;
        }

        if ($segundoFator === null && $this->exigencia->obrigatorio($contaId)) {
            return;
        }

        $sessao = $request->session();
        SessaoDoPainel::elevar($sessao);

        // Com um único acesso, a organização já fica escolhida e a elevação vai para a auditoria dela.
        $organizacoes = $this->acessos->organizacoes($contaId);
        $organizacaoId = count($organizacoes) === 1 ? $organizacoes[0]->id : null;
        if ($organizacaoId !== null) {
            SessaoDoPainel::escolherOrganizacao($sessao, $organizacaoId);
        }

        $this->registrar->executar($contaId, $organizacaoId, 'login', $segundoFator, (string) $request->ip());
    }
}
