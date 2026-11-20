<?php

namespace App\Modules\Autorizacao\Application\Solicitacoes;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\Actions\Resultado;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;

/** Recusa com justificativa obrigatória (OIK-ADR-0012 §8); para reduzir o pedido, recusa-se e pede-se de novo (§9). */
#[RequerPermissao(Permissao::SolicitacoesPermissaoAprovar)]
#[AuditaCom(EventoAuditoria::SolicitacaoPermissaoRecusada)]
final readonly class RecusarSolicitacaoAction
{
    public function __construct(
        private Decisoes $decisoes,
        private SolicitacoesDePermissao $solicitacoes,
    ) {}

    public function executar(string $uuid, string $motivo, ?int $versao): Resultado
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

        $this->solicitacoes->decidir($solicitacao['id'], 'recusada', $ator->acessoId, $motivo);
        $this->decisoes->governanca->auditar(EventoAuditoria::SolicitacaoPermissaoRecusada, 'solicitacao_permissao', $uuid, [
            ...Decisoes::metadados($solicitacao['itens']),
            'solicitante_acesso_id' => $solicitacao['solicitante_acesso_id'],
        ]);
        Decisoes::registrarLog('authz.permission_request_rejected', $uuid, 'recusada');

        return Resultado::ok();
    }
}
