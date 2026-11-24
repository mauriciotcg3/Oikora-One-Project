<?php

namespace App\Modules\Auditoria;

use App\Modules\Auditoria\Contracts\OperacoesDesfaziveis;
use App\Modules\Auditoria\Infrastructure\Console\AplicarRetencaoAuditoria;
use App\Modules\Auditoria\Infrastructure\Console\CriarParticoesAuditoria;
use App\Modules\Auditoria\Infrastructure\OperacoesDesfaziveisPostgres;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AuditoriaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OperacoesDesfaziveis::class, OperacoesDesfaziveisPostgres::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        // Grupo `painel` definido por Autorizacao: sessão, elevação, organização (RLS) e ator.
        Route::middleware('painel')->prefix('api/v1/painel')->group(__DIR__.'/Presentation/rotas-painel.php');

        if ($this->app->runningInConsole()) {
            $this->commands([CriarParticoesAuditoria::class, AplicarRetencaoAuditoria::class]);
        }
    }
}
