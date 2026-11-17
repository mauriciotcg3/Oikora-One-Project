<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Server;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // O contrato é o `api/openapi.json` versionado (OIK-ADR-0006 §8): sem `/docs/api` exposto.
        Scramble::ignoreDefaultRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Servidor relativo: o documento não depende do APP_URL do ambiente que o gerou.
        Scramble::configure()->withDocumentTransformers(static function (OpenApi $openApi): void {
            $openApi->servers = [new Server('/api/v1')];
        });
    }
}
