<?php

namespace App\Modules\Pessoas\Application\Transferencias;

use App\Modules\Autorizacao\Contracts\QuemPode;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;

/**
 * Avisos de serviço da transferência (plan.md §3.6; OIK-ADR-0008 §12), registrados na transação do fato:
 * o pedido vai às contas com `pessoas.transferencias.aprovar` na origem; a decisão, a quem pediu.
 */
final readonly class AvisosDeTransferencia
{
    public function __construct(
        private Avisos $avisos,
        private QuemPode $quemPode,
        private AcessosDaOrganizacao $acessos,
    ) {}

    public function solicitada(string $transferenciaUuid, int $origemId): void
    {
        $contas = $this->acessos->contasAtivas($this->quemPode->naIgreja(Permissao::TransferenciasAprovar, $origemId));
        $this->registrar(TipoAviso::TransferenciaSolicitada, $transferenciaUuid, array_values(array_unique($contas)));
    }

    public function decidida(string $transferenciaUuid, int $solicitanteAcessoId): void
    {
        $this->registrar(TipoAviso::TransferenciaDecidida, $transferenciaUuid, array_values($this->acessos->contasAtivas([$solicitanteAcessoId])));
    }

    /** @param  list<int>  $contas */
    private function registrar(TipoAviso $tipo, string $transferenciaUuid, array $contas): void
    {
        if ($contas === []) {
            return;
        }

        $this->avisos->registrar(
            $tipo,
            array_map(static fn (int $conta): Destinatario => Destinatario::conta($conta, destino: "/painel/transferencias/{$transferenciaUuid}"), $contas),
            new Referencia('transferencia', $transferenciaUuid),
        );
    }
}
