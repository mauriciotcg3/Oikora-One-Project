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
 * A secretaria de origem recusa com justificativa (RN-08); a pessoa fica onde está, com o mesmo vínculo,
 * e quem pediu recebe `servico.transferencia_decidida`. A justificativa é texto livre: fica no pedido, não
 * na auditoria.
 */
#[RequerPermissao(Permissao::TransferenciasAprovar)]
#[AuditaCom(EventoAuditoria::TransferenciaRecusada)]
final readonly class RecusarTransferenciaAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Transferencias $transferencias,
        private DecisaoDeTransferencia $decisao,
        private AvisosDeTransferencia $avisos,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid, string $justificativa): Resultado
    {
        $pedido = $this->decisao->localizar($uuid);
        if ($pedido instanceof Resultado) {
            return $pedido;
        }

        $ator = $this->alcance->ator();
        $this->transferencias->recusar($pedido['id'], $ator->acessoId, $justificativa);
        $this->avisos->decidida($pedido['uuid'], $pedido['solicitada_por_acesso_id']);
        $this->auditoria->registrar(
            evento: EventoAuditoria::TransferenciaRecusada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pedido['pessoa_uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pedido['igreja_origem_id'],
            metadados: [
                'transferencia_id' => $pedido['uuid'],
                'igreja_origem_id' => $pedido['igreja_origem_id'],
                'igreja_destino_id' => $pedido['igreja_destino_id'],
            ],
        );

        return Resultado::ok(['uuid' => $pedido['uuid'], 'estado' => 'recusada']);
    }
}
