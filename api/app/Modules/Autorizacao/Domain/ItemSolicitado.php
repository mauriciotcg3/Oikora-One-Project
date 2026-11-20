<?php

namespace App\Modules\Autorizacao\Domain;

/** Uma permissão pedida num escopo (OIK-ADR-0012 §2). `escopoId` é nulo no escopo `organizacao`. */
final readonly class ItemSolicitado
{
    public function __construct(
        public Permissao $permissao,
        public EscopoTipo $escopoTipo,
        public ?int $escopoId,
    ) {}

    public function escopo(): string
    {
        return $this->escopoId === null ? $this->escopoTipo->value : $this->escopoTipo->value.':'.$this->escopoId;
    }
}
