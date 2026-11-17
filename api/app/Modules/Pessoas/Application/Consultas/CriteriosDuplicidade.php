<?php

namespace App\Modules\Pessoas\Application\Consultas;

/** O que já foi digitado no cadastro (RN-23): nome + nascimento, CPF ou e-mail. */
final readonly class CriteriosDuplicidade
{
    public function __construct(
        public ?string $nome = null,
        public ?string $nascimento = null,
        public ?string $cpf = null,
        public ?string $email = null,
    ) {}

    public function vazio(): bool
    {
        return ($this->nome === null || $this->nascimento === null) && $this->cpf === null && $this->email === null;
    }
}
