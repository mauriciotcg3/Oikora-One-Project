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
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppCodigos;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkCodigos;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use App\Modules\Avisos\Infrastructure\Canais\Twilio\TwilioCodigos;
use App\Modules\Avisos\Infrastructure\Canais\Twilio\TwilioFalso;
use App\Modules\Avisos\Infrastructure\CanaisDeAvisoFalsos;
use App\Modules\Avisos\Infrastructure\Console\CriarParticoesEnvios;
use App\Modules\Avisos\Infrastructure\Console\VarrerEnvios;
use App\Modules\Avisos\Infrastructure\DestinosPelaConta;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AvisosServiceProvider extends ServiceProvider
{
    private static function reais(): bool
    {
        return config('oikora.codigos.provedores') === 'real';
    }

    public function register(): void
    {
        $this->app->bind(EnvioCodigo::class, EnvioCodigoPelaFila::class);
        $this->app->bind(Avisos::class, RegistrarAvisos::class);
        $this->app->bind(DestinosDeAviso::class, DestinosPelaConta::class);

        $this->app->singleton(PostmarkFalso::class);
        $this->app->singleton(MetaWhatsAppFalso::class);
        $this->app->singleton(TwilioFalso::class);

        // Códigos (T-11): provedores reais só com `oikora.codigos.provedores = real`; dev e testes usam os falsos.
        $this->app->bind(CanalDeEmail::class, fn (Application $app): CanalDeEmail => self::reais()
            ? $app->make(PostmarkCodigos::class) : $app->make(PostmarkFalso::class));
        $this->app->bind(CanalDeWhatsApp::class, fn (Application $app): CanalDeWhatsApp => self::reais()
            ? $app->make(MetaWhatsAppCodigos::class) : $app->make(MetaWhatsAppFalso::class));
        $this->app->bind(CanalDeSms::class, fn (Application $app): CanalDeSms => self::reais()
            ? $app->make(TwilioCodigos::class) : $app->make(TwilioFalso::class));

        // Avisos: os canais reais de aviso chegam com a T-18.
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
