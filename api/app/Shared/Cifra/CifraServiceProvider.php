<?php

namespace App\Shared\Cifra;

use Illuminate\Support\ServiceProvider;

class CifraServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CampoSensivelEncrypter::class, fn (): CampoSensivelEncrypter => new CampoSensivelEncrypter(
            (string) config('oikora.cifra.campos.chave_atual'),
            config('oikora.cifra.campos.chaves_anteriores', []),
        ));
    }
}
