<?php

namespace App\Modules\Identidade;

use App\Modules\Identidade\Application\AcessosDaConta;
use App\Modules\Identidade\Application\Codigos;
use App\Modules\Identidade\Application\Contas;
use App\Modules\Identidade\Application\Convites;
use App\Modules\Identidade\Application\EmissaoDeCodigos;
use App\Modules\Identidade\Application\EventosAutenticacao;
use App\Modules\Identidade\Application\ExigenciaDeDoisFatores;
use App\Modules\Identidade\Application\Totp;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use App\Modules\Identidade\Contracts\CodigosDeIdentidade;
use App\Modules\Identidade\Contracts\ContatosDaConta;
use App\Modules\Identidade\Infrastructure\AcessosDaContaPostgres;
use App\Modules\Identidade\Infrastructure\AcessosDaOrganizacaoPostgres;
use App\Modules\Identidade\Infrastructure\CodigosPostgres;
use App\Modules\Identidade\Infrastructure\Console\ExpirarConvites;
use App\Modules\Identidade\Infrastructure\ContasPostgres;
use App\Modules\Identidade\Infrastructure\ContatosDaContaPostgres;
use App\Modules\Identidade\Infrastructure\ConvitesPostgres;
use App\Modules\Identidade\Infrastructure\DoisFatoresPeloPapel;
use App\Modules\Identidade\Infrastructure\EventosAutenticacaoPostgres;
use App\Modules\Identidade\Infrastructure\ProvedorDeContas;
use App\Modules\Identidade\Infrastructure\ResolvedorOrganizacaoSessao;
use App\Modules\Identidade\Infrastructure\TotpGoogle2fa;
use App\Modules\Identidade\Presentation\AbrirSessao;
use App\Modules\Identidade\Presentation\ConvitesController;
use App\Modules\Identidade\Presentation\ElevacaoController;
use App\Modules\Identidade\Presentation\ElevacaoPainel;
use App\Modules\Identidade\Presentation\EntrarRequest;
use App\Modules\Identidade\Presentation\MeController;
use App\Modules\Identidade\Presentation\SemConteudo;
use App\Shared\Tenancy\Contracts\ResolvedorOrganizacaoAtiva;
use App\Shared\Tenancy\Http\ContextoOrganizacao;
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
        $this->app->bind(AcessosDaOrganizacao::class, AcessosDaOrganizacaoPostgres::class);
        $this->app->bind(ContatosDaConta::class, ContatosDaContaPostgres::class);
        $this->app->bind(Codigos::class, CodigosPostgres::class);
        $this->app->bind(CodigosDeIdentidade::class, EmissaoDeCodigos::class);
        $this->app->bind(ExigenciaDeDoisFatores::class, DoisFatoresPeloPapel::class);
        $this->app->bind(AcessosDaConta::class, AcessosDaContaPostgres::class);
        $this->app->bind(ResolvedorOrganizacaoAtiva::class, ResolvedorOrganizacaoSessao::class);
        $this->app->bind(Totp::class, TotpGoogle2fa::class);
        $this->app->bind(EventosAutenticacao::class, EventosAutenticacaoPostgres::class);
        $this->app->bind(Convites::class, ConvitesPostgres::class);
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
        Route::middleware(['web', 'auth:web'])->prefix('api/v1/conta')->group(__DIR__.'/Presentation/rotas-conta.php');

        // Organização ativa e `/me` (T-16): só sessão; a organização é revalidada a cada pedido.
        Route::middleware(['web', 'auth:web'])->prefix('api/v1/me')->group(function (): void {
            Route::get('/', [MeController::class, 'mostrar']);
            Route::put('organizacao-ativa', [MeController::class, 'escolherOrganizacao']);
        });

        // A elevação fica fora do grupo `painel` (que a exige) e grava a auditoria na organização ativa.
        Route::aliasMiddleware(ElevacaoPainel::ALIAS, ElevacaoPainel::class);
        Route::middleware(['web', 'auth:web', ContextoOrganizacao::class])
            ->post('api/v1/painel/elevacao', ElevacaoController::class);

        // Convite ao painel (T-22): emitir e revogar no grupo `painel`; ler e aceitar pelo link, com ou sem
        // sessão (quem já tem conta entra antes de aceitar).
        Route::middleware('painel')->prefix('api/v1/painel')->group(__DIR__.'/Presentation/rotas-painel.php');
        Route::middleware(['web', 'throttle:30,1'])->prefix('api/v1/convites')->group(function (): void {
            Route::get('{token}', [ConvitesController::class, 'mostrar'])->where('token', '[A-Za-z0-9]{48}');
            Route::post('{token}/aceitar', [ConvitesController::class, 'aceitar'])->where('token', '[A-Za-z0-9]{48}');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ExpirarConvites::class]);
        }
    }
}
