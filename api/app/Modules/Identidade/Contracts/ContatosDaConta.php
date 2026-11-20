<?php

namespace App\Modules\Identidade\Contracts;

/** Endereço de entrega de uma conta, lido na hora do envio (o payload do job leva só ids). */
interface ContatosDaConta
{
    /** @param  string  $canal  `email` ou `whatsapp` (celular); outro valor é tratado como e-mail */
    public function endereco(int $contaId, string $canal): ?string;
}
