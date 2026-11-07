<?php

namespace App\Modules\Identidade\Application;

use RuntimeException;

/** Motivo pelo qual o login não abriu sessão. A Presentation traduz cada caso para o contrato HTTP. */
final class FalhaDeLogin extends RuntimeException
{
    private function __construct(
        public readonly string $motivo,
        public readonly int $segundosDeEspera = 0,
        public readonly ?string $canal = null,
        public readonly string $destino = '',
    ) {
        parent::__construct($motivo);
    }

    public static function credenciais(): self
    {
        return new self('credenciais');
    }

    public static function muitasTentativas(int $segundosDeEspera): self
    {
        return new self('muitas_tentativas', segundosDeEspera: $segundosDeEspera);
    }

    public static function contaBloqueada(): self
    {
        return new self('conta_bloqueada');
    }

    public static function doisFatores(string $canal, string $destino): self
    {
        return new self('dois_fatores_necessario', canal: $canal, destino: $destino);
    }
}
