<?php

namespace App\Modules\Avisos\Contracts;

use InvalidArgumentException;

/** Quem recebe: uma conta, ou uma pessoa ainda sem conta. Endereço é lido só na hora do envio. */
final readonly class Destinatario
{
    private function __construct(
        public ?int $contaId,
        public ?int $pessoaId,
        public ?string $destino,
    ) {}

    /** @param  string|null  $destino  caminho do link universal aberto pelo aviso */
    public static function conta(int $contaId, ?int $pessoaId = null, ?string $destino = null): self
    {
        return new self($contaId, $pessoaId, $destino);
    }

    public static function pessoa(int $pessoaId, ?string $destino = null): self
    {
        return new self(null, $pessoaId, $destino);
    }

    public function chave(): string
    {
        return match (true) {
            $this->contaId !== null => 'conta:'.$this->contaId,
            $this->pessoaId !== null => 'pessoa:'.$this->pessoaId,
            default => throw new InvalidArgumentException('avisos.destinatario_vazio'),
        };
    }
}
