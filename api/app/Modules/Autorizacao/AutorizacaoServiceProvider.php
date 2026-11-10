<?php

namespace App\Modules\Autorizacao;

use App\Modules\Autorizacao\Application\AlcanceDaRequisicao;
use App\Modules\Autorizacao\Application\AtribuicoesDePermissao;
use App\Modules\Autorizacao\Application\Papeis;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Infrastructure\AtribuicoesDePermissaoPostgres;
use App\Modules\Autorizacao\Infrastructure\PapeisPostgres;
use App\Modules\Autorizacao\Presentation\ResolverAtor;
use App\Shared\Tenancy\Http\ContextoOrganizacao;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AutorizacaoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AtribuicoesDePermissao::class, AtribuicoesDePermissaoPostgres::class);
        $this->app->bind(Papeis::class, PapeisPostgres::class);

        // Uma instância por requisição ou job: o alcance nunca atravessa contextos.
        $this->app->scoped(AlcanceDaRequisicao::class);
        $this->app->alias(AlcanceDaRequisicao::class, AlcanceAtual::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        // Pilha das rotas `/api/v1/painel/*`, usada pelos módulos pelo nome: sessão, organização (RLS) e ator.
        Route::middlewareGroup('painel', ['web', 'auth:web', ContextoOrganizacao::class, ResolverAtor::class]);

        Route::middleware('painel')->prefix('api/v1/painel')->group(__DIR__.'/Presentation/rotas-painel.php');
    }
}
