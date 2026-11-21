<?php

namespace App\Modules\Pessoas\Application\Transferencias;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;

/**
 * A secretaria de origem aprova: a pessoa passa para a igreja de destino com o mesmo vínculo e a mesma
 * matrícula, e o fato entra na linha do tempo como `transferencia` (RN-08; CA-05, CA-22). O alcance segue
 * a igreja atual, então o destino passa a abrir a ficha e a origem deixa de abrir (OIK-ADR-0004 §6).
 */
#[RequerPermissao(Permissao::TransferenciasAprovar)]
#[AuditaCom(EventoAuditoria::TransferenciaAprovada)]
final readonly class AprovarTransferenciaAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Transferencias $transferencias,
        private DecisaoDeTransferencia $decisao,
        private AvisosDeTransferencia $avisos,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid): Resultado
    {
        $pedido = $this->decisao->localizar($uuid);
        if ($pedido instanceof Resultado) {
            return $pedido;
        }

        $ator = $this->alcance->ator();
        $this->transferencias->aprovar($pedido['id'], $ator->acessoId);
        $this->transferencias->moverPessoa($pedido['pessoa_id'], $pedido['igreja_destino_id']);
        $this->transferencias->registrarEvento($ator->organizacaoId, $pedido['pessoa_id'], [
            'transferencia' => $pedido['uuid'],
            'de' => $this->decisao->uuidDaIgreja($pedido['igreja_origem_id']),
            'para' => $this->decisao->uuidDaIgreja($pedido['igreja_destino_id']),
        ], $ator->acessoId);
        $this->avisos->decidida($pedido['uuid'], $pedido['solicitada_por_acesso_id']);
        $this->auditoria->registrar(
            evento: EventoAuditoria::TransferenciaAprovada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pedido['pessoa_uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pedido['igreja_origem_id'],
            campos: ['igreja_id'],
            metadados: [
                'transferencia_id' => $pedido['uuid'],
                'igreja_origem_id' => $pedido['igreja_origem_id'],
                'igreja_destino_id' => $pedido['igreja_destino_id'],
            ],
        );

        return Resultado::ok(['uuid' => $pedido['uuid'], 'estado' => 'aprovada']);
    }
}
