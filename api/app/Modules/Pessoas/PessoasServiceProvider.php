<?php

namespace App\Modules\Pessoas;

use App\Modules\Pessoas\Application\Actions\CadastroDePessoas;
use App\Modules\Pessoas\Application\Actions\EdicaoDeFichas;
use App\Modules\Pessoas\Application\Actions\RegistrosDaFicha;
use App\Modules\Pessoas\Application\Actions\VinculosDePessoas;
use App\Modules\Pessoas\Application\Configuracao\CamposObrigatorios;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Consultas\Fichas;
use App\Modules\Pessoas\Application\Consultas\ListaDePessoas;
use App\Modules\Pessoas\Application\Lixeira\Lixeira;
use App\Modules\Pessoas\Application\Lixeira\RemovedorDePessoas;
use App\Modules\Pessoas\Application\Mesclagem\Mesclagens;
use App\Modules\Pessoas\Application\Transferencias\Transferencias;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use App\Modules\Pessoas\Contracts\PessoasDoProvisionamento;
use App\Modules\Pessoas\Infrastructure\CadastroDePessoasEloquent;
use App\Modules\Pessoas\Infrastructure\Configuracao\CamposObrigatoriosPostgres;
use App\Modules\Pessoas\Infrastructure\Configuracao\ListasDeValoresPostgres;
use App\Modules\Pessoas\Infrastructure\Console\PurgarLixeiras;
use App\Modules\Pessoas\Infrastructure\Console\RecifrarCamposPessoas;
use App\Modules\Pessoas\Infrastructure\Consultas\BuscaDenominacaoPostgres;
use App\Modules\Pessoas\Infrastructure\Consultas\FichasEloquent;
use App\Modules\Pessoas\Infrastructure\Consultas\ListaDePessoasPostgres;
use App\Modules\Pessoas\Infrastructure\ContatosDaPessoaPostgres;
use App\Modules\Pessoas\Infrastructure\EdicaoDeFichasEloquent;
use App\Modules\Pessoas\Infrastructure\LixeiraPostgres;
use App\Modules\Pessoas\Infrastructure\MesclagensPostgres;
use App\Modules\Pessoas\Infrastructure\PessoasDoProvisionamentoEloquent;
use App\Modules\Pessoas\Infrastructure\RegistrosDaFichaPostgres;
use App\Modules\Pessoas\Infrastructure\RemovedorPessoas;
use App\Modules\Pessoas\Infrastructure\TransferenciasPostgres;
use App\Modules\Pessoas\Infrastructure\VinculosDePessoasEloquent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PessoasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BuscaDenominacao::class, BuscaDenominacaoPostgres::class);
        $this->app->bind(PessoasDoProvisionamento::class, PessoasDoProvisionamentoEloquent::class);
        $this->app->bind(Fichas::class, FichasEloquent::class);
        $this->app->bind(ListaDePessoas::class, ListaDePessoasPostgres::class);
        $this->app->bind(Mesclagens::class, MesclagensPostgres::class);
        $this->app->bind(Lixeira::class, LixeiraPostgres::class);
        $this->app->bind(RemovedorDePessoas::class, RemovedorPessoas::class);
        $this->app->bind(ListasDeValores::class, ListasDeValoresPostgres::class);
        $this->app->bind(CamposObrigatorios::class, CamposObrigatoriosPostgres::class);
        $this->app->bind(CadastroDePessoas::class, CadastroDePessoasEloquent::class);
        $this->app->bind(ContatosDaPessoa::class, ContatosDaPessoaPostgres::class);
        $this->app->bind(EdicaoDeFichas::class, EdicaoDeFichasEloquent::class);
        $this->app->bind(VinculosDePessoas::class, VinculosDePessoasEloquent::class);
        $this->app->bind(RegistrosDaFicha::class, RegistrosDaFichaPostgres::class);
        $this->app->bind(Transferencias::class, TransferenciasPostgres::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');

        // Grupo `painel` definido por Autorizacao: sessão, organização (RLS) e ator.
        Route::middleware('painel')->prefix('api/v1/painel')->group(__DIR__.'/Presentation/rotas-painel.php');

        if ($this->app->runningInConsole()) {
            $this->commands([RecifrarCamposPessoas::class, PurgarLixeiras::class]);
        }
    }
}
