<?php

namespace App\Modules\Jornadas;

use App\Modules\Jornadas\Infrastructure\Console\ExpirarRascunhos;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class JornadasServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');
        Route::middleware('painel')->prefix('api/v1/painel')->group(__DIR__.'/Presentation/rotas-painel.php');
        if ($this->app->runningInConsole()) {
            $this->commands([ExpirarRascunhos::class]);
        }
    }
}
