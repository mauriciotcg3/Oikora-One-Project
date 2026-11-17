<?php

namespace App\Modules\Identidade\Application;

use RuntimeException;

/** Motivo pelo qual a elevação do painel não abriu. A Presentation traduz para o contrato HTTP. */
final class FalhaDeElevacao extends RuntimeException
{
    private function __construct(
        public readonly string $motivo,
        public readonly ?string $canal = null,
        public readonly ?string $destino = null,
    ) {
        parent::__construct($motivo);
    }

    public static function de(string $motivo): self
    {
        return new self($motivo);
    }

    public static function doisFatoresNecessario(string $canal, ?string $destino): self
    {
        return new self('dois_fatores_necessario', $canal, $destino);
    }
}
