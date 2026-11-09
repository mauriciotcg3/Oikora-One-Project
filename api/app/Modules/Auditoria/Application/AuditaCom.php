<?php

namespace App\Modules\Auditoria\Application;

use App\Modules\Auditoria\Domain\EventoAuditoria;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AuditaCom
{
    public function __construct(public EventoAuditoria $evento) {}
}
