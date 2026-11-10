<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Contracts\ContatosDaConta;
use Illuminate\Support\Facades\DB;

/** Sob a RLS de `contas`: na organização em contexto, só enxerga conta com acesso ativo nela. */
final class ContatosDaContaPostgres implements ContatosDaConta
{
    public function endereco(int $contaId, string $canal): ?string
    {
        $coluna = $canal === 'whatsapp' ? 'celular' : 'email';
        $valor = DB::table('contas')->where('id', $contaId)->value($coluna);

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
