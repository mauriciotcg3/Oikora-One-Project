<?php

namespace App\Modules\Auditoria\Domain;

enum ResultadoAuditoria: string
{
    case Sucesso = 'sucesso';
    case Falha = 'falha';
}
