<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Transferencias\Transferencias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/** Pedidos de transferência na transação da requisição (contexto de RLS gravado). */
final readonly class TransferenciasPostgres implements Transferencias
{
    public function __construct(
        private BuscaDenominacao $busca,
        private AlcanceAtual $alcance,
    ) {}

    public function pessoa(string $uuid): ?array
    {
        $id = $this->busca->idPorUuid($uuid);
        $resumo = $id === null ? null : ($this->busca->resumos([$id])[$id] ?? null);

        return $id === null || $resumo === null ? null : ['id' => $id, 'uuid' => $resumo['uuid'], 'igreja_id' => $resumo['igreja_id']];
    }

    public function temPendente(int $pessoaId): bool
    {
        return DB::table('transferencias')->where('pessoa_id', $pessoaId)->where('estado', 'solicitada')->exists();
    }

    public function solicitar(int $organizacaoId, int $pessoaId, int $origemId, int $destinoId, int $solicitanteAcessoId): array
    {
        $uuid = (string) Str::uuid();
        $id = DB::table('transferencias')->insertGetId([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $pessoaId,
            'igreja_origem_id' => $origemId,
            'igreja_destino_id' => $destinoId,
            'estado' => 'solicitada',
            'solicitada_por_acesso_id' => $solicitanteAcessoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int) $id, 'uuid' => $uuid];
    }

    public function paraDecidir(string $uuid): ?array
    {
        $linha = DB::table('transferencias')->where('uuid', $uuid)->lockForUpdate()
            ->first(['id', 'uuid', 'pessoa_id', 'igreja_origem_id', 'igreja_destino_id', 'estado', 'solicitada_por_acesso_id']);
        if ($linha === null) {
            return null;
        }
        $pessoa = $this->busca->resumos([(int) $linha->pessoa_id])[(int) $linha->pessoa_id] ?? null;
        if ($pessoa === null) {
            return null;
        }

        return [
            'id' => (int) $linha->id,
            'uuid' => (string) $linha->uuid,
            'pessoa_id' => (int) $linha->pessoa_id,
            'pessoa_uuid' => $pessoa['uuid'],
            'igreja_origem_id' => (int) $linha->igreja_origem_id,
            'igreja_destino_id' => (int) $linha->igreja_destino_id,
            'estado' => (string) $linha->estado,
            'solicitada_por_acesso_id' => (int) $linha->solicitada_por_acesso_id,
        ];
    }

    public function aprovar(int $id, int $decisorAcessoId): void
    {
        $this->decidir($id, $decisorAcessoId, 'aprovada', null);
    }

    public function recusar(int $id, int $decisorAcessoId, string $justificativa): void
    {
        $this->decidir($id, $decisorAcessoId, 'recusada', $justificativa);
    }

    public function moverPessoa(int $pessoaId, int $destinoId): void
    {
        // A autorização já foi conferida na origem; quem aprova pode não ter `pessoas.ver` (filtro da
        // Pessoa), então a mudança de igreja roda como rotina de sistema da organização em contexto.
        $organizacaoId = $this->alcance->ator()->organizacaoId;
        $this->alcance->comoSistema($organizacaoId, static function () use ($pessoaId, $destinoId): void {
            Pessoa::query()->whereKey($pessoaId)->firstOrFail()->forceFill(['igreja_id' => $destinoId])->save();
        });
    }

    public function registrarEvento(int $organizacaoId, int $pessoaId, array $dados, int $autorAcessoId): void
    {
        DB::table('eventos_linha_do_tempo')->insert([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $pessoaId,
            'tipo' => 'transferencia',
            'ocorrido_em' => now(),
            'autor_acesso_id' => $autorAcessoId,
            'origem' => 'painel',
            'dados' => json_encode($dados, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function visiveis(array $origens, array $destinos): array
    {
        if ($origens === [] && $destinos === []) {
            return [];
        }

        $linhas = DB::table('transferencias')
            ->where(static function ($consulta) use ($origens, $destinos): void {
                $consulta->whereIn('igreja_origem_id', $origens)->orWhereIn('igreja_destino_id', $destinos);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['uuid', 'pessoa_id', 'igreja_origem_id', 'igreja_destino_id', 'estado', 'justificativa', 'created_at', 'decidida_em']);
        $pessoas = $this->busca->resumos(array_values($linhas->pluck('pessoa_id')->map(static fn (mixed $id): int => (int) $id)->unique()->all()));

        return array_values($linhas->map(static fn (stdClass $linha): array => [
            'uuid' => (string) $linha->uuid,
            'pessoa_uuid' => $pessoas[(int) $linha->pessoa_id]['uuid'] ?? '',
            'pessoa_nome' => $pessoas[(int) $linha->pessoa_id]['nome'] ?? '',
            'igreja_origem_id' => (int) $linha->igreja_origem_id,
            'igreja_destino_id' => (int) $linha->igreja_destino_id,
            'estado' => (string) $linha->estado,
            'justificativa' => $linha->justificativa === null ? null : (string) $linha->justificativa,
            'created_at' => (string) $linha->created_at,
            'decidida_em' => $linha->decidida_em === null ? null : (string) $linha->decidida_em,
        ])->all());
    }

    private function decidir(int $id, int $decisorAcessoId, string $estado, ?string $justificativa): void
    {
        DB::table('transferencias')->where('id', $id)->update([
            'estado' => $estado,
            'decidida_por_acesso_id' => $decisorAcessoId,
            'decidida_em' => now(),
            'justificativa' => $justificativa,
            'updated_at' => now(),
        ]);
    }
}
