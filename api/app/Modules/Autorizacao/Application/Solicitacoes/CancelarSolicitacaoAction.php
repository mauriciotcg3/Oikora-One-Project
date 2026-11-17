<?php

namespace App\Modules\Autorizacao\Application\Solicitacoes;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\Actions\Governanca;
use App\Modules\Autorizacao\Application\Actions\Resultado;
use App\Modules\Autorizacao\Application\RequerPermissao;

/** Quem pediu cancela a própria solicitação enquanto ela está pendente (OIK-ADR-0012; CA-31). */
#[RequerPermissao(null)]
#[AuditaCom(EventoAuditoria::SolicitacaoPermissaoCancelada)]
final readonly class CancelarSolicitacaoAction
{
    public function __construct(
        private Governanca $governanca,
        private SolicitacoesDePermissao $solicitacoes,
    ) {}

    public function executar(string $uuid): Resultado
    {
        $solicitacao = $this->solicitacoes->travarPorUuid($uuid);
        if ($solicitacao === null) {
            return Resultado::falha(404, 'solicitacao_inexistente');
        }

        if ($solicitacao['solicitante_acesso_id'] !== $this->governanca->alcance->ator()->acessoId) {
            return Resultado::falha(403, 'authz.denied');
        }

        if ($solicitacao['estado'] !== 'pendente') {
            return Resultado::falha(409, 'solicitacao_permissao_alterada');
        }

        $this->solicitacoes->decidir($solicitacao['id'], 'cancelada', null, null);
        $this->governanca->auditar(EventoAuditoria::SolicitacaoPermissaoCancelada, 'solicitacao_permissao', $uuid, Decisoes::metadados($solicitacao['itens']));
        Decisoes::registrarLog('authz.permission_request_cancelled', $uuid, 'cancelada');

        return Resultado::ok();
    }
}
