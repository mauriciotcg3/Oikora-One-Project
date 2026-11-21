<?php

namespace App\Modules\Avisos\Application;

interface EnviosExternos
{
    public function localizar(string $provedor, string $idExterno): ?IdentidadeEnvioExterno;
}
