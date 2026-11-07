<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Application\Jobs\EnviarCodigo;
use App\Modules\Avisos\Contracts\EnvioCodigo;

final class EnvioCodigoPelaFila implements EnvioCodigo
{
    public function enviar(int $codigoId): void
    {
        EnviarCodigo::dispatch($codigoId);
    }
}
