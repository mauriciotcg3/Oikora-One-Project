<?php

namespace App\Modules\Organizacao;

use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Organizacao\Infrastructure\CatalogoIgrejasPostgres;
use App\Modules\Organizacao\Infrastructure\ConsultaOrganizacoesPostgres;
use App\Shared\Tenancy\Contracts\OrganizacaoDisponivel;
use Illuminate\Support\ServiceProvider;

final class OrganizacaoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConsultaOrganizacoesPostgres::class);
        $this->app->alias(ConsultaOrganizacoesPostgres::class, ConsultaOrganizacoes::class);
        $this->app->alias(ConsultaOrganizacoesPostgres::class, OrganizacaoDisponivel::class);
        $this->app->bind(CatalogoIgrejas::class, CatalogoIgrejasPostgres::class);
    }
}
