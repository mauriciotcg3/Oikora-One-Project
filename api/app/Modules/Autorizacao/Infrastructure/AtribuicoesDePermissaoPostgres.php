<?php

namespace App\Modules\Autorizacao\Infrastructure;

use App\Modules\Autorizacao\Application\AtribuicoesDePermissao;
use Illuminate\Support\Facades\DB;

final class AtribuicoesDePermissaoPostgres implements AtribuicoesDePermissao
{
    public function doAcesso(int $organizacaoId, int $acessoId): array
    {
        /** @var list<array{permissao: string, escopo_tipo: string, escopo_id: int|null}> $linhas */
        $linhas = DB::table('atribuicoes_papel as atribuicao')
            ->join('papel_permissoes as permissao', function ($join): void {
                $join->on('permissao.organizacao_id', '=', 'atribuicao.organizacao_id')
                    ->on('permissao.papel_id', '=', 'atribuicao.papel_id');
            })
            ->where('atribuicao.organizacao_id', $organizacaoId)
            ->where('atribuicao.acesso_id', $acessoId)
            ->select(['permissao.permissao', 'atribuicao.escopo_tipo', 'atribuicao.escopo_id'])
            ->distinct()
            ->get()
            ->map(static fn (object $linha): array => [
                'permissao' => (string) $linha->permissao,
                'escopo_tipo' => (string) $linha->escopo_tipo,
                'escopo_id' => $linha->escopo_id === null ? null : (int) $linha->escopo_id,
            ])
            ->all();

        return $linhas;
    }
}
