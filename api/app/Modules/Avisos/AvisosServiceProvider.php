<?php

namespace App\Modules\Avisos;

use App\Modules\Avisos\Application\CanaisDeAviso;
use App\Modules\Avisos\Application\CanalDeEmail;
use App\Modules\Avisos\Application\CanalDeSms;
use App\Modules\Avisos\Application\CanalDeWhatsApp;
use App\Modules\Avisos\Application\DestinosDeAviso;
use App\Modules\Avisos\Application\EnvioCodigoPelaFila;
use App\Modules\Avisos\Application\RegistrarAvisos;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\EnvioCodigo;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use App\Modules\Avisos\Infrastructure\Canais\Twilio\TwilioFalso;
use App\Modules\Avisos\Infrastructure\CanaisDeAvisoFalsos;
use App\Modules\Avisos\Infrastructure\Console\CriarParticoesEnvios;
use App\Modules\Avisos\Infrastructure\Console\VarrerEnvios;
use App\Modules\Avisos\Infrastructure\DestinosPelaConta;
use Illuminate\Support\ServiceProvider;

class AvisosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EnvioCodigo::class, EnvioCodigoPelaFila::class);
        $this->app->bind(Avisos::class, RegistrarAvisos::class);
        $this->app->bind(DestinosDeAviso::class, DestinosPelaConta::class);

        // Sem provedor real por enquanto: os três canais usam Adapters falsos.
        $this->app->singleton(PostmarkFalso::class);
        $this->app->singleton(MetaWhatsAppFalso::class);
        $this->app->singleton(TwilioFalso::class);
        $this->app->alias(PostmarkFalso::class, CanalDeEmail::class);
        $this->app->alias(MetaWhatsAppFalso::class, CanalDeWhatsApp::class);
        $this->app->alias(TwilioFalso::class, CanalDeSms::class);
        $this->app->bind(CanaisDeAviso::class, CanaisDeAvisoFalsos::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([VarrerEnvios::class, CriarParticoesEnvios::class]);
        }
    }
}
