<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\Totp;
use Illuminate\Contracts\Cache\Repository;
use PragmaRX\Google2FA\Google2FA;

final class TotpGoogle2fa implements Totp
{
    /** Maior que a janela de validade do código, que é de cerca de 90 segundos. */
    private const MEMORIA_DE_USO_SEGUNDOS = 300;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly Repository $cache,
    ) {}

    public function novoSegredo(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function urlDeProvisionamento(string $rotulo, string $segredo): string
    {
        return $this->google2fa->getQRCodeUrl('Oikora One', $rotulo, $segredo);
    }

    public function conferir(int $contaId, string $segredo, string $codigo): bool
    {
        $chave = 'identidade:totp:ultimo:'.$contaId;
        $ultimo = $this->cache->get($chave);

        $momento = $this->google2fa->verifyKeyNewer($segredo, $codigo, is_int($ultimo) ? $ultimo : 0);

        if (! is_int($momento)) {
            return false;
        }

        $this->cache->put($chave, $momento, self::MEMORIA_DE_USO_SEGUNDOS);

        return true;
    }
}
