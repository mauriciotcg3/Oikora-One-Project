<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Contracts\AvisosDaPessoa;
use Illuminate\Support\Facades\DB;

final readonly class RemoverAvisosDaPessoa implements AvisosDaPessoa
{
    public function remover(int $pessoaId, string $modo): void
    {
        $avisos = DB::table('avisos')->where('pessoa_id', $pessoaId)->select('id');

        if ($modo === 'apagar') {
            DB::table('envios')->whereIn('aviso_id', $avisos)->delete();
            DB::table('avisos')->where('pessoa_id', $pessoaId)->delete();

            return;
        }

        DB::table('envios')->whereIn('aviso_id', $avisos)->update(['destino_mascarado' => null, 'destino_hash' => null, 'updated_at' => now()]);
        DB::table('avisos')->where('pessoa_id', $pessoaId)->update(['destino' => null, 'updated_at' => now()]);
    }
}
