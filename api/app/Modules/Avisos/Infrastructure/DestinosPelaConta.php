<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\DestinosDeAviso;
use App\Modules\Identidade\Contracts\ContatosDaConta;

/**
 * Destino pela conta (Contract de Identidade). Pessoa sem conta (contato da ficha) entra com o canal
 * de convite da T-22 e as regras de canal da T-18; até lá, sem endereço o envio falha como definitivo.
 */
final readonly class DestinosPelaConta implements DestinosDeAviso
{
    public function __construct(private ContatosDaConta $contatos) {}

    public function endereco(?int $contaId, ?int $pessoaId, string $canal): ?string
    {
        return $contaId === null ? null : $this->contatos->endereco($contaId, $canal);
    }
}
