<?php

namespace App\Modules\Autorizacao\Application;

interface AtribuicoesDePermissao
{
    /** @return list<array{permissao: string, escopo_tipo: string, escopo_id: int|null}> */
    public function doAcesso(int $organizacaoId, int $acessoId): array;
}
