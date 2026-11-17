<?php

namespace App\Modules\Pessoas\Application\Configuracao;

/** Saída das Actions de configuração. Negação não lança exceção, como nas Actions de Autorização. */
final readonly class Resultado
{
    /** @param  array<string, mixed>  $dados */
    private function __construct(
        public int $status,
        public ?string $codigo,
        public array $dados,
    ) {}

    /** @param  array<string, mixed>  $dados */
    public static function ok(array $dados = [], int $status = 200): self
    {
        return new self($status, null, $dados);
    }

    public static function falha(int $status, string $codigo): self
    {
        return new self($status, $codigo, []);
    }

    public function sucesso(): bool
    {
        return $this->codigo === null;
    }
}
