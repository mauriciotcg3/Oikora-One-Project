<?php

use App\Modules\Avisos\AvisosServiceProvider;
use App\Modules\Identidade\IdentidadeServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\Tenancy\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    IdentidadeServiceProvider::class,
    AvisosServiceProvider::class,
];
