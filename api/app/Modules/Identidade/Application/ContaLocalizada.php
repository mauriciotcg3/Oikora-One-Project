<?php

namespace App\Modules\Identidade\Application;

/** Retorno mínimo da via estreita de login (`identidade_localizar_conta`, OIK-ADR-0003 §3). */
final readonly class ContaLocalizada
{
    public function __construct(
        public int $id,
        public string $hashSenha,
        public string $estado,
        public ?string $canalDoisFatores,
    ) {}

    public function bloqueada(): bool
    {
        return $this->estado === 'bloqueada';
    }
}
