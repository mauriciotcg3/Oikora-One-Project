<?php

namespace App\Modules\Avisos\Application;

/** Link assinado de descadastro em um clique (RFC 8058; OIK-ADR-0008 §9, §13). */
interface LinksDeDescadastro
{
    public function paraPessoa(string $organizacaoUuid, string $pessoaUuid, string $canal): string;
}
