<?php

use App\Modules\Auditoria\AuditoriaServiceProvider;
use App\Modules\Autorizacao\AutorizacaoServiceProvider;
use App\Modules\Avisos\AvisosServiceProvider;
use App\Modules\Identidade\IdentidadeServiceProvider;
use App\Modules\Jornadas\JornadasServiceProvider;
use App\Modules\Organizacao\OrganizacaoServiceProvider;
use App\Modules\Pessoas\PessoasServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\Arquivos\ArquivosServiceProvider;
use App\Shared\Cifra\CifraServiceProvider;
use App\Shared\Observabilidade\ObservabilidadeServiceProvider;
use App\Shared\Tenancy\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    ObservabilidadeServiceProvider::class,
    CifraServiceProvider::class,
    ArquivosServiceProvider::class,
    OrganizacaoServiceProvider::class,
    AutorizacaoServiceProvider::class,
    AuditoriaServiceProvider::class,
    IdentidadeServiceProvider::class,
    JornadasServiceProvider::class,
    AvisosServiceProvider::class,
    PessoasServiceProvider::class,
];
