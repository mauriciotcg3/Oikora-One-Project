<?php

namespace App\Modules\Identidade\Application\Actions;

/** Saída das Actions de convite e acesso. Negação não lança exceção, como nas Actions de Autorização. */
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

    /** @param  array<string, mixed>  $dados  detalhes da recusa (ex.: mensagem e próximo passo) */
    public static function falha(int $status, string $codigo, array $dados = []): self
    {
        return new self($status, $codigo, $dados);
    }

    /**
     * 422 por campo, no formato padrão do Laravel (`errors`), montado pela borda.
     *
     * @param  array<string, string>  $erros  campo → código do erro
     */
    public static function invalido(array $erros): self
    {
        return new self(422, 'campos_invalidos', ['erros' => $erros]);
    }

    public function sucesso(): bool
    {
        return $this->codigo === null;
    }
}
