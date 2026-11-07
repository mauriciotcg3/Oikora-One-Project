<?php

namespace App\Modules\Identidade\Application;

use RuntimeException;

/** Motivo pelo qual o segundo fator não abriu a sessão. A Presentation traduz para o contrato HTTP. */
final class FalhaDeDesafio extends RuntimeException
{
    private function __construct(public readonly string $motivo)
    {
        parent::__construct($motivo);
    }

    public static function codigoIncorreto(): self
    {
        return new self('codigo_incorreto');
    }

    public static function muitasTentativas(): self
    {
        return new self('muitas_tentativas');
    }

    public static function contaBloqueada(): self
    {
        return new self('conta_bloqueada');
    }
}
