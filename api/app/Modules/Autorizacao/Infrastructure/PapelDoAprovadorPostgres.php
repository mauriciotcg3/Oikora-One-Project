<?php

namespace App\Modules\Autorizacao\Infrastructure;

use App\Modules\Autorizacao\Application\Solicitacoes\PapelDoAprovador;
use Illuminate\Support\Facades\DB;

final class PapelDoAprovadorPostgres implements PapelDoAprovador
{
    public function administrador(int $organizacaoId, int $acessoId): bool
    {
        return DB::table('atribuicoes_papel as atribuicao')
            ->join('papeis as papel', function ($join): void {
                $join->on('papel.organizacao_id', '=', 'atribuicao.organizacao_id')->on('papel.id', '=', 'atribuicao.papel_id');
            })
            ->where('atribuicao.organizacao_id', $organizacaoId)
            ->where('atribuicao.acesso_id', $acessoId)
            ->where('papel.sistema', 'administrador')
            ->exists();
    }
}
