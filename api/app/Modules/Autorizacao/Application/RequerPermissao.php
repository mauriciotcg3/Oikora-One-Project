<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Autorizacao\Domain\Permissao;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class RequerPermissao
{
    public function __construct(public Permissao $permissao) {}
}
