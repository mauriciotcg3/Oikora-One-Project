<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Pessoas\Application\Lixeira\Lixeira;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LixeiraPostgres implements Lixeira
{
    public const MOTIVO_MESCLAGEM = 'mesclagem';

    public function localizar(string $uuid, bool $bloquear = false): ?array
    {
        $consulta = Pessoa::query()->where('uuid', $uuid);
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
        $linha = $consulta->toBase()->first(['id', 'uuid', 'igreja_id', 'excluida_em', 'exclusao_motivo']);

        return $linha === null ? null : [
            'id' => (int) $linha->id,
            'uuid' => (string) $linha->uuid,
            'igreja_id' => (int) $linha->igreja_id,
            'excluida_em' => $linha->excluida_em === null ? null : (string) $linha->excluida_em,
            'exclusao_motivo' => $linha->exclusao_motivo === null ? null : (string) $linha->exclusao_motivo,
        ];
    }

    public function excluir(int $pessoaId, string $motivo, int $porAcessoId): void
    {
        Pessoa::query()->whereKey($pessoaId)->update([
            'excluida_em' => now(),
            'excluida_por_acesso_id' => $porAcessoId,
            'exclusao_motivo' => $motivo,
            'updated_at' => now(),
        ]);
    }

    public function recuperar(int $pessoaId): void
    {
        Pessoa::query()->whereKey($pessoaId)->update([
            'excluida_em' => null,
            'excluida_por_acesso_id' => null,
            'exclusao_motivo' => null,
            'updated_at' => now(),
        ]);
    }

    public function listar(array $igrejas, int $pagina, int $porPagina): array
    {
        $consulta = Pessoa::query()
            ->whereIn('igreja_id', $igrejas)
            ->whereNotNull('excluida_em')
            ->where(fn ($q) => $q->whereNull('exclusao_motivo')->orWhere('exclusao_motivo', '<>', self::MOTIVO_MESCLAGEM))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('exclusoes_executadas as e')->whereColumn('e.pessoa_id', 'pessoas.id'));

        $total = (clone $consulta)->count();
        $itens = array_values($consulta->orderByDesc('excluida_em')->orderBy('id')->forPage($pagina, $porPagina)
            ->toBase()->get(['uuid', 'nome', 'igreja_id', 'excluida_em', 'exclusao_motivo'])
            ->map(static fn (object $linha): array => [
                'uuid' => (string) $linha->uuid,
                'nome' => (string) $linha->nome,
                'igreja_id' => (int) $linha->igreja_id,
                'excluida_em' => (string) $linha->excluida_em,
                'motivo' => $linha->exclusao_motivo === null ? null : (string) $linha->exclusao_motivo,
            ])->all());

        return ['itens' => $itens, 'total' => $total];
    }

    public function vencidas(int $dias, array $protegidas): array
    {
        return Pessoa::query()
            ->whereNotNull('excluida_em')
            ->where('excluida_em', '<', now()->subDays($dias))
            ->whereNotIn('id', $protegidas)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('exclusoes_executadas as e')->whereColumn('e.pessoa_id', 'pessoas.id'))
            ->orderBy('id')
            ->pluck('uuid', 'id')
            ->mapWithKeys(static fn (mixed $uuid, mixed $id): array => [(int) $id => (string) $uuid])
            ->all();
    }

    public function evento(int $organizacaoId, int $pessoaId, string $tipo, ?int $autorAcessoId, array $dados = []): void
    {
        DB::table('eventos_linha_do_tempo')->insert([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $pessoaId,
            'tipo' => $tipo,
            'ocorrido_em' => now(),
            'autor_acesso_id' => $autorAcessoId,
            'origem' => 'painel',
            'dados' => $dados === [] ? null : json_encode($dados, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
