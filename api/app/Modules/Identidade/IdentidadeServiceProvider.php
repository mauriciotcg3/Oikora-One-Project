<?php

namespace App\Modules\Identidade;

use App\Modules\Identidade\Application\Codigos;
use App\Modules\Identidade\Application\Contas;
use App\Modules\Identidade\Application\EmissaoDeCodigos;
use App\Modules\Identidade\Application\EventosAutenticacao;
use App\Modules\Identidade\Contracts\CodigosDeIdentidade;
use App\Modules\Identidade\Infrastructure\CodigosPostgres;
use App\Modules\Identidade\Infrastructure\ContasPostgres;
use App\Modules\Identidade\Infrastructure\EventosAutenticacaoPostgres;
use App\Modules\Identidade\Infrastructure\ProvedorDeContas;
use App\Modules\Identidade\Presentation\AbrirSessao;
use App\Modules\Identidade\Presentation\EntrarRequest;
use App\Modules\Identidade\Presentation\SemConteudo;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

class IdentidadeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->bind(Contas::class, ContasPostgres::class);
        $this->app->bind(Codigos::class, CodigosPostgres::class);
        $this->app->bind(CodigosDeIdentidade::class, EmissaoDeCodigos::class);
        $this->app->bind(EventosAutenticacao::class, EventosAutenticacaoPostgres::class);
        $this->app->bind(LoginRequest::class, EntrarRequest::class);
        $this->app->singleton(LoginResponse::class, SemConteudo::class);
        $this->app->singleton(LogoutResponse::class, SemConteudo::class);
    }

    public function boot(): void
    {
        Auth::provider('contas', fn (Application $app): ProvedorDeContas => $app->make(ProvedorDeContas::class));

        Fortify::authenticateThrough(fn (): array => [AbrirSessao::class]);

        // A consulta ao HIBP envia só um prefixo do hash (plan.md §4, U-29).
        Password::defaults(fn (): Password => Password::min(8)->uncompromised());

        // Grupo `web`: login e logout sempre têm sessão e CSRF, venha a requisição de onde vier.
        Route::middleware('web')->prefix('api/v1/auth')->group(__DIR__.'/Presentation/rotas.php');
    }
}
