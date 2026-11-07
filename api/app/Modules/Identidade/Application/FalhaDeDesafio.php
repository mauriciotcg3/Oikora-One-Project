<?php

namespace App\Modules\Identidade\Application;

use RuntimeException;

/** Motivo pelo qual o código não foi aceito. A Presentation traduz para o contrato HTTP. */
final class FalhaDeDesafio extends RuntimeException
{
    private function __construct(public readonly string $motivo, public readonly ?string $canal = null)
    {
        parent::__construct($motivo);
    }

    public static function codigoIncorreto(?string $canal = null): self
    {
        return new self('codigo_incorreto', $canal);
    }

    public static function muitasTentativas(?string $canal = null): self
    {
        return new self('muitas_tentativas', $canal);
    }

    public static function contaBloqueada(): self
    {
        return new self('conta_bloqueada');
    }
}
