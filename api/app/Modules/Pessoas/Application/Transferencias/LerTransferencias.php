<?php

namespace App\Modules\Pessoas\Application\Transferencias;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;

/**
 * `GET /painel/transferencias`: o pedido é visível à secretaria de destino (quem pede) e à de origem
 * (quem aprova), OIK-ADR-0004 §6. A pessoa sai só com a projeção restrita da busca na denominação.
 */
final readonly class LerTransferencias
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Transferencias $transferencias,
        private CatalogoIgrejas $igrejas,
    ) {}

    /** @return list<array<string, mixed>> */
    public function executar(): array
    {
        $aprova = $this->alcance->igrejasCom(Permissao::TransferenciasAprovar);
        $pede = $this->alcance->igrejasCom(Permissao::TransferenciasSolicitar);
        $pedidos = $this->transferencias->visiveis($aprova, $pede);
        $igrejas = $this->igrejas->resumo(array_values(array_unique([
            ...array_column($pedidos, 'igreja_origem_id'),
            ...array_column($pedidos, 'igreja_destino_id'),
        ])));

        return array_map(static fn (array $pedido): array => [
            'uuid' => $pedido['uuid'],
            'pessoa' => ['uuid' => $pedido['pessoa_uuid'], 'nome' => $pedido['pessoa_nome']],
            'origem' => $igrejas[$pedido['igreja_origem_id']] ?? null,
            'destino' => $igrejas[$pedido['igreja_destino_id']] ?? null,
            'estado' => $pedido['estado'],
            'justificativa' => $pedido['justificativa'],
            'solicitada_em' => $pedido['created_at'],
            'decidida_em' => $pedido['decidida_em'],
            'acoes' => [
                'decidir' => $pedido['estado'] === 'solicitada' && in_array($pedido['igreja_origem_id'], $aprova, true),
            ],
        ], $pedidos);
    }
}
