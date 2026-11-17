<?php

namespace App\Modules\Autorizacao\Application\Solicitacoes;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\Actions\Resultado;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;

/**
 * Aprova a versão pedida, inteira (OIK-ADR-0012 §4, §9): a linha fica travada até o fim da transação, e
 * uma decisão que chega depois encontra o pedido já decidido (409). A unicidade da concessão por
 * solicitação garante no máximo uma, mesmo com decisões concorrentes.
 */
#[RequerPermissao(Permissao::SolicitacoesPermissaoAprovar)]
#[AuditaCom(EventoAuditoria::SolicitacaoPermissaoAprovada)]
final readonly class AprovarSolicitacaoAction
{
    public function __construct(
        private Decisoes $decisoes,
        private SolicitacoesDePermissao $solicitacoes,
    ) {}

    public function executar(string $uuid, ?int $versao): Resultado
    {
        if ($negada = $this->decisoes->decisorNegado()) {
            return $negada;
        }

        $solicitacao = $this->solicitacoes->travarPorUuid($uuid);
        if ($solicitacao === null) {
            return Resultado::falha(404, 'solicitacao_inexistente');
        }

        if ($solicitacao['estado'] !== 'pendente' || ($versao !== null && $versao !== $solicitacao['versao'])) {
            return Resultado::falha(409, 'solicitacao_permissao_alterada');
        }

        $ator = $this->decisoes->governanca->alcance->ator();
        if ($solicitacao['solicitante_acesso_id'] === $ator->acessoId) {
            return Resultado::falha(403, 'autoaprovacao_negada');
        }

        if ($foraDoTeto = $this->decisoes->foraDoTeto($solicitacao['itens'], $uuid)) {
            return $foraDoTeto;
        }

        $this->solicitacoes->decidir($solicitacao['id'], 'aprovada', $ator->acessoId, null);
        $concessao = $this->solicitacoes->conceder(
            $ator->organizacaoId,
            $solicitacao['id'],
            $solicitacao['solicitante_acesso_id'],
            $solicitacao['itens'],
            $solicitacao['duracao_horas'],
        );

        $this->decisoes->governanca->auditar(EventoAuditoria::SolicitacaoPermissaoAprovada, 'solicitacao_permissao', $uuid, [
            ...Decisoes::metadados($solicitacao['itens']),
            'solicitante_acesso_id' => $solicitacao['solicitante_acesso_id'],
            'duracao_horas' => $solicitacao['duracao_horas'],
            'concessao_id' => $concessao['uuid'],
            'expira_em' => $concessao['expira_em'],
        ]);
        Decisoes::registrarLog('authz.permission_request_approved', $uuid, 'aprovada');

        return Resultado::ok(['concessao' => $concessao['uuid']]);
    }
}
