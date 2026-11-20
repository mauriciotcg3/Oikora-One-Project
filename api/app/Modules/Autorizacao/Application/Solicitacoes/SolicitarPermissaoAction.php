<?php

namespace App\Modules\Autorizacao\Application\Solicitacoes;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\Actions\Governanca;
use App\Modules\Autorizacao\Application\Actions\Resultado;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\ItemSolicitado;

/**
 * Pedido de permissões temporárias (OIK-ADR-0012 §2, §5, §6; CA-31): basta o acesso ativo; o pedido não
 * dá capacidade nenhuma até ser aprovado. Governança e exportação não são solicitáveis; duração de 1 a 72h.
 */
#[RequerPermissao(null)]
#[AuditaCom(EventoAuditoria::PermissaoSolicitada)]
final readonly class SolicitarPermissaoAction
{
    public const DURACAO_MAXIMA_HORAS = 72;

    public function __construct(
        private Governanca $governanca,
        private SolicitacoesDePermissao $solicitacoes,
    ) {}

    /** @param list<ItemSolicitado> $itens */
    public function executar(array $itens, int $duracaoHoras, string $justificativa): Resultado
    {
        if ($duracaoHoras < 1 || $duracaoHoras > self::DURACAO_MAXIMA_HORAS) {
            return Resultado::falha(422, 'duracao_concessao_invalida');
        }

        foreach ($itens as $item) {
            if (! $item->permissao->solicitavel()) {
                return Resultado::falha(422, 'permissao_nao_solicitavel');
            }
        }

        $ator = $this->governanca->alcance->ator();
        $solicitacao = $this->solicitacoes->criar($ator->organizacaoId, $ator->acessoId, $itens, $duracaoHoras, $justificativa);

        $this->governanca->auditar(EventoAuditoria::PermissaoSolicitada, 'solicitacao_permissao', $solicitacao['uuid'], [
            ...Decisoes::metadados($itens),
            'duracao_horas' => $duracaoHoras,
        ]);
        Decisoes::registrarLog('authz.permission_requested', $solicitacao['uuid'], 'pendente');

        return Resultado::ok(['uuid' => $solicitacao['uuid']], 201);
    }
}
