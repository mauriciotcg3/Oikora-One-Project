<?php

namespace App\Modules\Autorizacao;

use App\Modules\Autorizacao\Application\AtribuicoesDePermissao;
use App\Modules\Autorizacao\Infrastructure\AtribuicoesDePermissaoPostgres;
use Illuminate\Support\ServiceProvider;

final class AutorizacaoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AtribuicoesDePermissao::class, AtribuicoesDePermissaoPostgres::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');
    }
}
