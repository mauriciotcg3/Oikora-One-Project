<?php

namespace App\Modules\Autorizacao;

use App\Modules\Autorizacao\Application\AlcanceDaRequisicao;
use App\Modules\Autorizacao\Application\AtribuicoesDePermissao;
use App\Modules\Autorizacao\Application\ConcessaoDeAcessoPorPapel;
use App\Modules\Autorizacao\Application\CriarPapeisProntos;
use App\Modules\Autorizacao\Application\Papeis;
use App\Modules\Autorizacao\Application\QuemPodePeloAlcance;
use App\Modules\Autorizacao\Application\Solicitacoes\PapelDoAprovador;
use App\Modules\Autorizacao\Application\Solicitacoes\SolicitacoesDePermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\ConcessaoDeAcesso;
use App\Modules\Autorizacao\Contracts\PapeisDaConta;
use App\Modules\Autorizacao\Contracts\PapeisProntos;
use App\Modules\Autorizacao\Contracts\QuemPode;
use App\Modules\Autorizacao\Infrastructure\AtribuicoesDePermissaoPostgres;
use App\Modules\Autorizacao\Infrastructure\PapeisDaContaPostgres;
use App\Modules\Autorizacao\Infrastructure\PapeisPostgres;
use App\Modules\Autorizacao\Infrastructure\PapelDoAprovadorPostgres;
use App\Modules\Autorizacao\Infrastructure\SolicitacoesDePermissaoPostgres;
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
        $this->app->bind(PapeisDaConta::class, PapeisDaContaPostgres::class);
        $this->app->bind(SolicitacoesDePermissao::class, SolicitacoesDePermissaoPostgres::class);
        $this->app->bind(PapelDoAprovador::class, PapelDoAprovadorPostgres::class);
        $this->app->bind(QuemPode::class, QuemPodePeloAlcance::class);
        $this->app->bind(ConcessaoDeAcesso::class, ConcessaoDeAcessoPorPapel::class);
        $this->app->bind(PapeisProntos::class, CriarPapeisProntos::class);

        // Uma instância por requisição ou job: o alcance nunca atravessa contextos.
        $this->app->scoped(AlcanceDaRequisicao::class);
        $this->app->alias(AlcanceDaRequisicao::class, AlcanceAtual::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        // Pilha das rotas `/api/v1/painel/*`, usada pelos módulos pelo nome: sessão, elevação (alias que
        // Identidade registra, OIK-ADR-0003 §9), organização (RLS) e ator com ao menos uma atribuição.
        Route::middlewareGroup('painel', ['web', 'auth:web', 'elevacao.painel', ContextoOrganizacao::class, ResolverAtor::class]);

        Route::middleware('painel')->prefix('api/v1/painel')->group(__DIR__.'/Presentation/rotas-painel.php');
    }
}
