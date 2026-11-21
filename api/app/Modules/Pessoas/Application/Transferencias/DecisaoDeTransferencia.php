<?php

namespace App\Modules\Pessoas\Application\Transferencias;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Application\Actions\FichaParaEditar;
use App\Modules\Pessoas\Application\Configuracao\Resultado;

/**
 * Conferências comuns de aprovar e recusar: o pedido existe, ainda está `solicitada` e quem decide tem
 * `pessoas.transferencias.aprovar` na igreja de origem. A secretaria de destino não decide o próprio
 * pedido, porque não tem a permissão na origem (403).
 */
final readonly class DecisaoDeTransferencia
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Transferencias $transferencias,
        private CatalogoIgrejas $igrejas,
    ) {}

    /** @return array{id: int, uuid: string, pessoa_id: int, pessoa_uuid: string, igreja_origem_id: int, igreja_destino_id: int, estado: string, solicitada_por_acesso_id: int}|Resultado */
    public function localizar(string $uuid): array|Resultado
    {
        $pedido = $this->transferencias->paraDecidir($uuid);
        if ($pedido === null) {
            return Resultado::falha(404, 'transferencia_inexistente');
        }
        if (! $this->alcance->pode(Permissao::TransferenciasAprovar, $pedido['igreja_origem_id'])) {
            return FichaParaEditar::negar(Permissao::TransferenciasAprovar);
        }
        if ($pedido['estado'] !== 'solicitada') {
            return Resultado::falha(422, 'transferencia_ja_decidida', ['mensagem' => 'Este pedido já foi decidido.']);
        }

        return $pedido;
    }

    public function uuidDaIgreja(int $id): ?string
    {
        return $this->igrejas->resumo([$id])[$id]['uuid'] ?? null;
    }
}
