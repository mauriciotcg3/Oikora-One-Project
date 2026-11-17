<?php

namespace App\Modules\Autorizacao\Application\Solicitacoes;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\Actions\Resultado;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;

/** O administrador revoga uma concessão vigente; ela para de valer na requisição seguinte (OIK-ADR-0012 §10). */
#[RequerPermissao(Permissao::SolicitacoesPermissaoAprovar)]
#[AuditaCom(EventoAuditoria::ConcessaoPermissaoRevogada)]
final readonly class RevogarConcessaoAction
{
    public function __construct(
        private Decisoes $decisoes,
        private SolicitacoesDePermissao $solicitacoes,
    ) {}

    public function executar(string $uuid, ?string $motivo): Resultado
    {
        if ($negada = $this->decisoes->decisorNegado()) {
            return $negada;
        }

        $concessao = $this->solicitacoes->travarConcessaoVigente($uuid);
        if ($concessao === null) {
            return Resultado::falha(409, 'concessao_encerrada');
        }

        $this->solicitacoes->revogar($concessao['id'], $this->decisoes->governanca->alcance->ator()->acessoId, $motivo);
        $this->decisoes->governanca->auditar(EventoAuditoria::ConcessaoPermissaoRevogada, 'concessao_permissao', $uuid, [
            ...Decisoes::metadados($concessao['itens']),
            'solicitacao_id' => $concessao['solicitacao_id'],
            'acesso_alvo_id' => $concessao['acesso_id'],
            'expira_em' => $concessao['expira_em'],
        ]);
        Decisoes::registrarLog('authz.permission_grant_revoked', $uuid, 'revogada');

        return Resultado::ok();
    }
}
