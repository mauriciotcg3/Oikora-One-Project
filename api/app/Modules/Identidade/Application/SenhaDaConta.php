<?php

namespace App\Modules\Identidade\Application;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Hashing\Hasher;

/** Reautenticação e troca de senha da própria conta (OIK-ADR-0003 §11). */
final class SenhaDaConta
{
    public function __construct(
        private readonly Contas $contas,
        private readonly EventosAutenticacao $eventos,
        private readonly Hasher $senhas,
        private readonly RateLimiter $limitador,
    ) {}

    /** @throws FalhaDeDesafio */
    public function confirmar(int $contaId, string $senha): bool
    {
        $chave = 'identidade:confirmar-senha:'.$contaId;

        if ($this->limitador->tooManyAttempts($chave, (int) config('oikora.senha.tentativas_de_confirmacao'))) {
            throw FalhaDeDesafio::muitasTentativas();
        }

        if (! $this->senhas->check($senha, $this->contas->hashDaSenha($contaId))) {
            $this->limitador->hit($chave, (int) config('oikora.senha.janela_segundos'));

            return false;
        }

        $this->limitador->clear($chave);

        return true;
    }

    public function trocar(int $contaId, string $senha, string $ip): void
    {
        $this->contas->redefinirSenha($contaId, $this->senhas->make($senha));
        $this->eventos->registrar('auth.senha.trocada', $contaId, Resumo::de((string) $contaId), Resumo::de($ip));
    }
}
