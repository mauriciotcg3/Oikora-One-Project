<?php

namespace App\Modules\Pessoas\Application\Lixeira;

use RuntimeException;

/** Sem `lgpd.modo_exclusao` em produção, nenhuma ficha é removida (OIK-ADR-0005 §8; OPEN-013). */
final class ExclusaoBloqueada extends RuntimeException
{
    public const CODIGO = 'lgpd.erasure.blocked_no_policy';

    public function __construct()
    {
        parent::__construct(self::CODIGO);
    }
}
