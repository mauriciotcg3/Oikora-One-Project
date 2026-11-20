<?php

namespace App\Shared\Arquivos;

use Illuminate\Support\ServiceProvider;

class ArquivosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ArquivosPrivados::class, ArquivosNoDisco::class);
    }
}
