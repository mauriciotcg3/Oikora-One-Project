<?php

namespace App\Modules\Avisos\Contracts;

use App\Modules\Avisos\Domain\TipoAviso;

/**
 * Única porta de envio para os outros módulos (OIK-ADR-0008 §1; COMM-004). Chamada dentro da transação
 * do fato: grava o aviso (um por destinatário) e um envio `pendente` por canal; o job só sai no commit.
 * Repetir com a mesma (tipo, referência, destinatário, chave) não cria outro aviso.
 */
interface Avisos
{
    /** @param  list<Destinatario>  $destinatarios */
    public function registrar(TipoAviso $tipo, array $destinatarios, Referencia $referencia, string $chave = ''): void;
}
