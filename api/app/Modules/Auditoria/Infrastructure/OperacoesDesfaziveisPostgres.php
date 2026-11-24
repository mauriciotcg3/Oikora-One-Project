<?php

namespace App\Modules\Auditoria\Infrastructure;

use App\Modules\Auditoria\Contracts\OperacoesDesfaziveis;
use App\Shared\Cifra\CampoSensivelEncrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OperacoesDesfaziveisPostgres implements OperacoesDesfaziveis
{
    public function __construct(private CampoSensivelEncrypter $cifra) {}

    public function registrar(int $organizacaoId, string $tipo, ?int $autorAcessoId, array $snapshot, array $afetados): array
    {
        $uuid = (string) Str::uuid();
        $agora = now();
        $expiraEm = $agora->copy()->addDays(self::VALIDADE_DIAS);

        $id = (int) DB::table('operacoes_desfaziveis')->insertGetId([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'tipo' => $tipo,
            'autor_acesso_id' => $autorAcessoId,
            'estado' => 'executada',
            'executada_em' => $agora,
            'expira_em' => $expiraEm,
            'snapshot' => $this->cifra->encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'afetados' => json_encode($afetados === [] ? (object) [] : $afetados, JSON_THROW_ON_ERROR),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return ['id' => $id, 'uuid' => $uuid, 'expira_em' => $expiraEm->toAtomString()];
    }

    public function porUuid(string $uuid, bool $bloquear = false): ?array
    {
        $consulta = DB::table('operacoes_desfaziveis')->where('uuid', $uuid);
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
        $linha = $consulta->first();
        if ($linha === null) {
            return null;
        }

        $snapshot = null;
        if (is_string($linha->snapshot)) {
            /** @var array<string, mixed> $snapshot */
            $snapshot = json_decode($this->cifra->decryptString($linha->snapshot), true, flags: JSON_THROW_ON_ERROR);
        }
        /** @var array<string, mixed> $afetados */
        $afetados = json_decode((string) $linha->afetados, true, flags: JSON_THROW_ON_ERROR);

        return [
            'id' => (int) $linha->id,
            'uuid' => (string) $linha->uuid,
            'tipo' => (string) $linha->tipo,
            'estado' => (string) $linha->estado,
            'executada_em' => (string) $linha->executada_em,
            'expira_em' => (string) $linha->expira_em,
            'snapshot' => $snapshot,
            'afetados' => $afetados,
        ];
    }

    public function encerrar(int $id, string $estado, ?int $porAcessoId = null): void
    {
        DB::table('operacoes_desfaziveis')->where('id', $id)->update([
            'estado' => $estado,
            'snapshot' => null,
            'desfeita_em' => $estado === 'desfeita' ? now() : null,
            'desfeita_por_acesso_id' => $porAcessoId,
            'updated_at' => now(),
        ]);
    }

    public function protegidos(string $tipo, string $chave): array
    {
        return array_values(DB::table('operacoes_desfaziveis')
            ->where('tipo', $tipo)
            ->where('estado', 'executada')
            ->selectRaw('(afetados ->> ?)::bigint as valor', [$chave])
            ->pluck('valor')
            ->filter(static fn (mixed $valor): bool => $valor !== null)
            ->map(static fn (mixed $valor): int => (int) $valor)
            ->all());
    }

    public function expirarDaPessoa(int $pessoaId): int
    {
        return DB::table('operacoes_desfaziveis')
            ->where('estado', 'executada')
            ->where(fn ($q) => $q->whereRaw("(afetados ->> 'mantida_id')::bigint = ?", [$pessoaId])
                ->orWhereRaw("(afetados ->> 'absorvida_id')::bigint = ?", [$pessoaId]))
            ->update(['estado' => 'expirada', 'snapshot' => null, 'updated_at' => now()]);
    }
}
