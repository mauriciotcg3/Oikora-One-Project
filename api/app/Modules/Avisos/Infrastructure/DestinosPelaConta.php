<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\DestinosDeAviso;
use App\Modules\Identidade\Contracts\ContatosDaConta;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;

/**
 * Destino pela conta (Contract de Identidade) ou, para pessoa sem conta, pelo contato da ficha (Contract
 * de Pessoas, onde também fica o descadastro). O celular da conta é o WhatsApp dela.
 */
final readonly class DestinosPelaConta implements DestinosDeAviso
{
    public function __construct(
        private ContatosDaConta $contas,
        private ContatosDaPessoa $pessoas,
    ) {}

    public function endereco(?int $contaId, ?int $pessoaId, string $canal, bool $respeitarDescadastro = false): ?string
    {
        if ($contaId !== null) {
            return $this->contas->endereco($contaId, $canal);
        }

        return $pessoaId === null ? null : $this->pessoas->endereco($pessoaId, $canal, $respeitarDescadastro);
    }
}
