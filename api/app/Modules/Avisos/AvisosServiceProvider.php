<?php

namespace App\Modules\Avisos;

use App\Modules\Avisos\Application\CanalDeEmail;
use App\Modules\Avisos\Application\CanalDeSms;
use App\Modules\Avisos\Application\CanalDeWhatsApp;
use App\Modules\Avisos\Application\EnvioCodigoPelaFila;
use App\Modules\Avisos\Contracts\EnvioCodigo;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use App\Modules\Avisos\Infrastructure\Canais\Twilio\TwilioFalso;
use Illuminate\Support\ServiceProvider;

class AvisosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EnvioCodigo::class, EnvioCodigoPelaFila::class);

        // Sem provedor real por enquanto: os três canais usam Adapters falsos.
        $this->app->singleton(PostmarkFalso::class);
        $this->app->singleton(MetaWhatsAppFalso::class);
        $this->app->singleton(TwilioFalso::class);
        $this->app->alias(PostmarkFalso::class, CanalDeEmail::class);
        $this->app->alias(MetaWhatsAppFalso::class, CanalDeWhatsApp::class);
        $this->app->alias(TwilioFalso::class, CanalDeSms::class);
    }
}
