<?php

namespace App\Modules\Avisos;

use App\Modules\Avisos\Application\CanaisDeAviso;
use App\Modules\Avisos\Application\CanalDeEmail;
use App\Modules\Avisos\Application\CanalDeSms;
use App\Modules\Avisos\Application\CanalDeWhatsApp;
use App\Modules\Avisos\Application\DestinosDeAviso;
use App\Modules\Avisos\Application\EnvioCodigoPelaFila;
use App\Modules\Avisos\Application\LinksDeDescadastro;
use App\Modules\Avisos\Application\NumerosWhatsApp;
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
use App\Modules\Avisos\Infrastructure\CanaisDeAvisoReais;
use App\Modules\Avisos\Infrastructure\Console\CriarParticoesEnvios;
use App\Modules\Avisos\Infrastructure\Console\VarrerEnvios;
use App\Modules\Avisos\Infrastructure\DestinosPelaConta;
use App\Modules\Avisos\Infrastructure\LinkDeDescadastroAssinado;
use App\Modules\Avisos\Infrastructure\NumerosWhatsAppIndisponiveis;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AvisosServiceProvider extends ServiceProvider
{
    private static function reais(): bool
    {
        return config('oikora.codigos.provedores') === 'real';
    }

    private static function avisosReais(): bool
    {
        return config('oikora.avisos.provedores') === 'real';
    }

    public function register(): void
    {
        $this->app->bind(EnvioCodigo::class, EnvioCodigoPelaFila::class);
        $this->app->bind(Avisos::class, RegistrarAvisos::class);
        $this->app->bind(DestinosDeAviso::class, DestinosPelaConta::class);
        $this->app->bind(LinksDeDescadastro::class, LinkDeDescadastroAssinado::class);
        // Número de WhatsApp da organização: `whatsapp_numeros` chega com a T-21; até lá, nenhum ativo.
        $this->app->bind(NumerosWhatsApp::class, NumerosWhatsAppIndisponiveis::class);

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

        // Avisos (T-18): Postmark e número da organização na Meta só com `oikora.avisos.provedores = real`.
        $this->app->bind(CanaisDeAviso::class, fn (Application $app): CanaisDeAviso => self::avisosReais()
            ? $app->make(CanaisDeAvisoReais::class) : $app->make(CanaisDeAvisoFalsos::class));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        // Sem sessão nem organização: a assinatura do link é a autorização (RFC 8058).
        Route::prefix('api/v1')->group(__DIR__.'/Presentation/rotas-publicas.php');

        if ($this->app->runningInConsole()) {
            $this->commands([VarrerEnvios::class, CriarParticoesEnvios::class]);
        }
    }
}
