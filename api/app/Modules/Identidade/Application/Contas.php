<?php

namespace App\Modules\Identidade\Application;

interface Contas
{
    public function localizar(string $identificador): ?ContaLocalizada;

    /** Destino mascarado do código; vazio quando o canal não envia mensagem (TOTP). */
    public function destinoDoCodigo(int $contaId, string $canal): string;
}
