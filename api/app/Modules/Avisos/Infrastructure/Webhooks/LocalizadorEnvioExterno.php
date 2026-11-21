<?php

namespace App\Modules\Avisos\Infrastructure\Webhooks;

use App\Modules\Avisos\Application\EnviosExternos;
use App\Modules\Avisos\Application\IdentidadeEnvioExterno;
use Illuminate\Support\Facades\DB;

/** Consulta privilegiada mínima: só identifica o tenant e o envio pelo id opaco do provedor. */
final class LocalizadorEnvioExterno implements EnviosExternos
{
    public function localizar(string $provedor, string $idExterno): ?IdentidadeEnvioExterno
    {
        $envio = DB::connection('pgsql_dono')
            ->table('envios')
            ->select(['id', 'organizacao_id', 'aviso_id', 'aviso_criado_em'])
            ->where('provedor', $provedor)
            ->where('id_externo', $idExterno)
            ->first();
        if ($envio === null) {
            return null;
        }

        return new IdentidadeEnvioExterno(
            (int) $envio->id,
            (int) $envio->organizacao_id,
            (int) $envio->aviso_id,
            (string) $envio->aviso_criado_em,
        );
    }
}
