<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Autorizacao\Domain\Permissao;
use Attribute;

/**
 * Declaração de segurança de toda Action de escrita. `null` declara de forma explícita que basta o acesso
 * ativo do ator no painel, sem permissão do catálogo (ex.: pedir uma permissão temporária, OIK-ADR-0012 §2).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class RequerPermissao
{
    public function __construct(public ?Permissao $permissao) {}
}
