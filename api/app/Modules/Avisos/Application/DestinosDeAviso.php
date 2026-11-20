<?php

namespace App\Modules\Avisos\Application;

/** Endereço de entrega do destinatário, lido na hora do envio, nunca guardado em claro no envio. */
interface DestinosDeAviso
{
    /**
     * Conta: o contato da conta. Pessoa sem conta: o contato da ficha (WhatsApp só em celular marcado).
     * Com `$respeitarDescadastro` (tipos não obrigatórios), contato descadastrado não recebe.
     */
    public function endereco(?int $contaId, ?int $pessoaId, string $canal, bool $respeitarDescadastro = false): ?string;
}
