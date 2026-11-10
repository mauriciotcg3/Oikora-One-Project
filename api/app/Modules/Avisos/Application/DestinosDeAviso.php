<?php

namespace App\Modules\Avisos\Application;

/** Endereço de entrega do destinatário, lido na hora do envio, nunca guardado em claro no envio. */
interface DestinosDeAviso
{
    public function endereco(?int $contaId, ?int $pessoaId, string $canal): ?string;
}
