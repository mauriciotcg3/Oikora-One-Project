<?php

use App\Modules\Avisos\AvisosServiceProvider;
use App\Modules\Identidade\IdentidadeServiceProvider;
use App\Modules\Organizacao\OrganizacaoServiceProvider;
use App\Modules\Pessoas\PessoasServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\Cifra\CifraServiceProvider;
use App\Shared\Tenancy\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    CifraServiceProvider::class,
    OrganizacaoServiceProvider::class,
    IdentidadeServiceProvider::class,
    AvisosServiceProvider::class,
    PessoasServiceProvider::class,
];
