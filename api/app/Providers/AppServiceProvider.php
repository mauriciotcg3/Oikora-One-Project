<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Server;
use Illuminate\Routing\Route;
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
        $configuracao = Scramble::configure();
        $configuracao->withDocumentTransformers(static function (OpenApi $openApi): void {
            $openApi->servers = [new Server('/api/v1')];
        });

        // Rotas `storage.*` (disco `arquivos` servido pelo Laravel em dev e testes) não são contrato: em
        // produção o arquivo vai e volta direto do S3 pela URL assinada, e o documento não pode mudar com o disco.
        $configuracao->routes(static fn (Route $rota): bool => $configuracao->apiPath()->matches($rota->uri)
            && ! str_starts_with((string) $rota->getName(), 'storage.'));
    }
}
