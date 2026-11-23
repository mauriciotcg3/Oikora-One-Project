<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\Convites;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class ConvitesPostgres implements Convites
{
    private const COLUNAS = ['id', 'uuid', 'pessoa_id', 'papel_id', 'escopo_tipo', 'escopo_id', 'estado', 'expira_em'];

    public function pendente(int $pessoaId): bool
    {
        return DB::table('convites')
            ->where('pessoa_id', $pessoaId)
            ->where('estado', 'enviado')
            ->where('expira_em', '>', now())
            ->exists();
    }

    public function emitir(int $organizacaoId, int $pessoaId, int $papelId, string $escopoTipo, ?int $escopoId, string $tokenHash, DateTimeImmutable $expiraEm, ?int $emissorAcessoId): array
    {
        $uuid = (string) Str::uuid();
        $id = DB::table('convites')->insertGetId([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $pessoaId,
            'papel_id' => $papelId,
            'escopo_tipo' => $escopoTipo,
            'escopo_id' => $escopoId,
            'token_hash' => $tokenHash,
            'expira_em' => $expiraEm,
            'estado' => 'enviado',
            'emitido_por_acesso_id' => $emissorAcessoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int) $id, 'uuid' => $uuid];
    }

    public function porUuid(string $uuid): ?array
    {
        return $this->lido(DB::table('convites')->where('uuid', $uuid)->lockForUpdate()->first(self::COLUNAS));
    }

    public function doToken(string $tokenHash): ?array
    {
        return $this->lido(DB::table('convites')->where('token_hash', $tokenHash)->lockForUpdate()->first(self::COLUNAS));
    }

    public function revogar(int $id, int $porAcessoId): void
    {
        DB::table('convites')->where('id', $id)->update([
            'estado' => 'revogado',
            'revogado_em' => now(),
            'revogado_por_acesso_id' => $porAcessoId,
            'updated_at' => now(),
        ]);
    }

    public function aceitar(int $id, int $acessoId): void
    {
        DB::table('convites')->where('id', $id)->update([
            'estado' => 'aceito',
            'aceito_em' => now(),
            'acesso_id' => $acessoId,
            'updated_at' => now(),
        ]);
    }

    public function expirarVencidos(): int
    {
        // Rotina de sistema sobre todas as organizações: só muda o estado, sem ler dado da Pessoa.
        return DB::connection('pgsql_dono')->table('convites')
            ->where('estado', 'enviado')
            ->where('expira_em', '<=', now())
            ->update(['estado' => 'expirado', 'updated_at' => now()]);
    }

    public function organizacaoDoToken(string $tokenHash): ?int
    {
        $id = DB::scalar('select identidade_organizacao_do_convite(?)', [$tokenHash]);

        return $id === null ? null : (int) $id;
    }

    public function naOrganizacao(int $organizacaoId, ?int $contaId, Closure $acao): mixed
    {
        return DB::transaction(function () use ($organizacaoId, $contaId, $acao): mixed {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
            if ($contaId !== null) {
                $this->usarConta($contaId);
            }

            return $acao();
        });
    }

    public function usarConta(int $contaId): void
    {
        DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);
    }

    public function pessoaTemAcesso(int $pessoaId): bool
    {
        return DB::table('acessos')->where('pessoa_id', $pessoaId)->where('estado', 'ativo')->exists();
    }

    public function contaTemAcesso(int $contaId): bool
    {
        return DB::table('acessos')->where('conta_id', $contaId)->where('estado', 'ativo')->exists();
    }

    public function criarAcesso(int $organizacaoId, int $contaId, int $pessoaId): array
    {
        $uuid = (string) Str::uuid();
        $id = DB::table('acessos')->insertGetId([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'conta_id' => $contaId,
            'pessoa_id' => $pessoaId,
            'estado' => 'ativo',
            'origem' => 'convite',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int) $id, 'uuid' => $uuid];
    }

    public function acessoPorUuid(string $uuid): ?array
    {
        $acesso = DB::table('acessos')->where('uuid', $uuid)->lockForUpdate()->first(['id', 'uuid', 'conta_id', 'estado']);

        return $acesso === null ? null : [
            'id' => (int) $acesso->id,
            'uuid' => (string) $acesso->uuid,
            'conta_id' => (int) $acesso->conta_id,
            'ativo' => $acesso->estado === 'ativo',
        ];
    }

    public function revogarAcesso(int $id, int $porAcessoId): void
    {
        DB::table('acessos')->where('id', $id)->update([
            'estado' => 'revogado',
            'revogado_em' => now(),
            'revogado_por_acesso_id' => $porAcessoId,
            'updated_at' => now(),
        ]);
    }

    public function criarConta(?string $email, ?string $celular, string $hashSenha): int
    {
        return (int) DB::scalar('select identidade_criar_conta(?, ?, ?)', [$email, $celular, $hashSenha]);
    }

    public function identificadorEmUso(string $identificador): bool
    {
        return (bool) DB::scalar('select identidade_identificador_em_uso(?)', [$identificador]);
    }

    /** @return array{id: int, uuid: string, pessoa_id: int, papel_id: int, escopo_tipo: string, escopo_id: int|null, estado: string, expira_em: string}|null */
    private function lido(?stdClass $linha): ?array
    {
        return $linha === null ? null : [
            'id' => (int) $linha->id,
            'uuid' => (string) $linha->uuid,
            'pessoa_id' => (int) $linha->pessoa_id,
            'papel_id' => (int) $linha->papel_id,
            'escopo_tipo' => (string) $linha->escopo_tipo,
            'escopo_id' => $linha->escopo_id === null ? null : (int) $linha->escopo_id,
            'estado' => (string) $linha->estado,
            'expira_em' => (string) $linha->expira_em,
        ];
    }
}
