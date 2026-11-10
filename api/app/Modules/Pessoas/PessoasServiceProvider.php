<?php

namespace App\Modules\Pessoas;

use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Consultas\Fichas;
use App\Modules\Pessoas\Infrastructure\Console\RecifrarCamposPessoas;
use App\Modules\Pessoas\Infrastructure\Consultas\BuscaDenominacaoPostgres;
use App\Modules\Pessoas\Infrastructure\Consultas\FichasEloquent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PessoasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BuscaDenominacao::class, BuscaDenominacaoPostgres::class);
        $this->app->bind(Fichas::class, FichasEloquent::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        // Grupo `painel` definido por Autorizacao: sessão, organização (RLS) e ator.
        Route::middleware('painel')->prefix('api/v1/painel')->group(__DIR__.'/Presentation/rotas-painel.php');

        if ($this->app->runningInConsole()) {
            $this->commands([RecifrarCamposPessoas::class]);
        }
    }
}
