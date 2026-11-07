<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\EventosAutenticacao;
use DateTimeInterface;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

final class EventosAutenticacaoPostgres implements EventosAutenticacao
{
    public function registrar(string $evento, ?int $contaId, string $identificadorResumo, string $ipResumo, ?string $canal = null): void
    {
        DB::table('eventos_autenticacao')->insert([
            'conta_id' => $contaId,
            'identificador_hash' => $identificadorResumo,
            'evento' => $evento,
            'ip_hash' => $ipResumo,
            'canal' => $canal,
            'request_id' => Context::get('request_id'),
            'ocorrido_em' => now(),
        ]);
    }

    public function canalDaUltimaRedefinicao(int $contaId, DateTimeInterface $desde): ?string
    {
        $canal = DB::table('eventos_autenticacao')
            ->where('conta_id', $contaId)
            ->where('evento', 'auth.senha.redefinida')
            ->where('ocorrido_em', '>=', $desde)
            ->orderByDesc('id')
            ->value('canal');

        return is_string($canal) ? $canal : null;
    }
}
