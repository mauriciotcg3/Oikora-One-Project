<?php

namespace App\Modules\Pessoas\Application\Transferencias;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Actions\FichaParaEditar;
use App\Modules\Pessoas\Application\Configuracao\Resultado;

/**
 * A secretaria de destino pede a transferência de uma pessoa de outra igreja da organização para a sua
 * (P FR-031, FLOW-009; RN-08). Só um pedido pendente por pessoa (U-21). Quem aprova na origem recebe o
 * aviso de serviço `servico.transferencia_solicitada` (OIK-ADR-0008 §12).
 */
#[RequerPermissao(Permissao::TransferenciasSolicitar)]
#[AuditaCom(EventoAuditoria::TransferenciaSolicitada)]
final readonly class SolicitarTransferenciaAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Transferencias $transferencias,
        private AvisosDeTransferencia $avisos,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $pessoaUuid, int $destinoId): Resultado
    {
        if (! $this->alcance->pode(Permissao::TransferenciasSolicitar, $destinoId)) {
            return FichaParaEditar::negar(Permissao::TransferenciasSolicitar);
        }

        $pessoa = $this->transferencias->pessoa($pessoaUuid);
        if ($pessoa === null) {
            return Resultado::invalido(['pessoa' => 'pessoa_inexistente']);
        }
        if ($pessoa['igreja_id'] === $destinoId) {
            return Resultado::falha(422, 'mesma_igreja', ['mensagem' => 'A pessoa já é desta igreja.']);
        }
        if ($this->transferencias->temPendente($pessoa['id'])) {
            return Resultado::falha(422, 'transferencia_pendente', [
                'mensagem' => 'Já existe um pedido de transferência pendente para esta pessoa.',
            ]);
        }

        $ator = $this->alcance->ator();
        $transferencia = $this->transferencias->solicitar($ator->organizacaoId, $pessoa['id'], $pessoa['igreja_id'], $destinoId, $ator->acessoId);
        $this->avisos->solicitada($transferencia['uuid'], $pessoa['igreja_id']);
        $this->auditoria->registrar(
            evento: EventoAuditoria::TransferenciaSolicitada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
            metadados: [
                'transferencia_id' => $transferencia['uuid'],
                'igreja_origem_id' => $pessoa['igreja_id'],
                'igreja_destino_id' => $destinoId,
            ],
        );

        return Resultado::ok(['uuid' => $transferencia['uuid'], 'estado' => 'solicitada'], 201);
    }
}
