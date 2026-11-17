<?php

namespace App\Modules\Autorizacao\Infrastructure;

use App\Modules\Autorizacao\Application\AtribuicoesDePermissao;
use Illuminate\Support\Facades\DB;

final class AtribuicoesDePermissaoPostgres implements AtribuicoesDePermissao
{
    public function doAcesso(int $organizacaoId, int $acessoId): array
    {
        $dePapeis = DB::table('atribuicoes_papel as atribuicao')
            ->join('papel_permissoes as permissao', function ($join): void {
                $join->on('permissao.organizacao_id', '=', 'atribuicao.organizacao_id')
                    ->on('permissao.papel_id', '=', 'atribuicao.papel_id');
            })
            ->where('atribuicao.organizacao_id', $organizacaoId)
            ->where('atribuicao.acesso_id', $acessoId)
            ->select(['permissao.permissao', 'atribuicao.escopo_tipo', 'atribuicao.escopo_id']);

        // Concessão temporária vigente (OIK-ADR-0012 §7, §10): o horário do banco decide, a cada leitura.
        $deConcessoes = DB::table('concessoes_permissao as concessao')
            ->join('concessao_permissoes as item', function ($join): void {
                $join->on('item.organizacao_id', '=', 'concessao.organizacao_id')
                    ->on('item.concessao_id', '=', 'concessao.id');
            })
            ->where('concessao.organizacao_id', $organizacaoId)
            ->where('concessao.acesso_id', $acessoId)
            ->whereNull('concessao.revogada_em')
            ->whereRaw('concessao.inicia_em <= now() and concessao.expira_em > now()')
            ->select(['item.permissao', 'item.escopo_tipo', 'item.escopo_id']);

        /** @var list<array{permissao: string, escopo_tipo: string, escopo_id: int|null}> $linhas */
        $linhas = $dePapeis->union($deConcessoes)
            ->get()
            ->map(static fn (object $linha): array => [
                'permissao' => (string) $linha->permissao,
                'escopo_tipo' => (string) $linha->escopo_tipo,
                'escopo_id' => $linha->escopo_id === null ? null : (int) $linha->escopo_id,
            ])
            ->all();

        return $linhas;
    }

    public function temPapel(int $organizacaoId, int $acessoId): bool
    {
        return DB::table('atribuicoes_papel')
            ->where('organizacao_id', $organizacaoId)
            ->where('acesso_id', $acessoId)
            ->exists();
    }
}
