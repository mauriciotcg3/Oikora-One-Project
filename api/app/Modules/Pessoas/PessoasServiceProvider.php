<?php

namespace App\Modules\Pessoas;

use App\Modules\Pessoas\Infrastructure\Console\RecifrarCamposPessoas;
use Illuminate\Support\ServiceProvider;

class PessoasServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([RecifrarCamposPessoas::class]);
        }
    }
}
