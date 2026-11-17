<?php

namespace App\Modules\Autorizacao\Application\Solicitacoes;

/** No MVP, só o administrador da organização decide solicitações (OIK-ADR-0012 §4). */
interface PapelDoAprovador
{
    public function administrador(int $organizacaoId, int $acessoId): bool;
}
