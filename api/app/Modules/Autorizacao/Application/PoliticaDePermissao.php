<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Autorizacao\Domain\Permissao;

final readonly class PoliticaDePermissao
{
    public function __construct(private Alcance $alcance) {}

    public function autoriza(
        int $organizacaoId,
        int $acessoId,
        Permissao $permissao,
        int $igrejaId,
    ): bool {
        return $this->alcance->permite($organizacaoId, $acessoId, $permissao, $igrejaId);
    }
}
