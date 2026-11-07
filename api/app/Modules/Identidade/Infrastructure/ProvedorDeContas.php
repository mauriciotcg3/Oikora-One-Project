<?php

namespace App\Modules\Identidade\Infrastructure;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Carrega a conta da sessão respeitando a RLS de identidade. O login por senha não passa por aqui:
 * ele usa a Action `Entrar`, então os métodos de credencial e de "lembrar de mim" não têm uso.
 */
final class ProvedorDeContas implements UserProvider
{
    public function __construct(private readonly ContasPostgres $contas) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->contas->daPropriaConta((int) $identifier);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void {}

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        return null;
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        return false;
    }

    /** @param array<string, mixed> $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void {}
}
