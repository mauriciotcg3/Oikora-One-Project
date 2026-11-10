<?php

namespace App\Modules\Autorizacao\Contracts;

/** Quem age: o acesso da conta na organização em contexto (OIK-ADR-0004 §4). */
final readonly class Ator
{
    public function __construct(
        public int $organizacaoId,
        public int $contaId,
        public int $acessoId,
    ) {}
}
