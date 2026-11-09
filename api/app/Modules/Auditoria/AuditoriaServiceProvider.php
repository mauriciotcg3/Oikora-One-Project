<?php

namespace App\Modules\Auditoria;

use App\Modules\Auditoria\Infrastructure\Console\AplicarRetencaoAuditoria;
use App\Modules\Auditoria\Infrastructure\Console\CriarParticoesAuditoria;
use Illuminate\Support\ServiceProvider;

final class AuditoriaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([CriarParticoesAuditoria::class, AplicarRetencaoAuditoria::class]);
        }
    }
}
