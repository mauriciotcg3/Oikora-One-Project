<?php

namespace App\Modules\Autorizacao\Infrastructure;

use App\Modules\Autorizacao\Application\Papeis;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/** @phpstan-import-type PapelLido from Papeis */
final class PapeisPostgres implements Papeis
{
    public function listar(): array
    {
        $papeis = DB::table('papeis')->orderBy('nome')->get(['id', 'uuid', 'nome', 'sistema']);
        $permissoes = $this->permissoesDe(array_values($papeis->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()));

        return array_values($papeis->map(fn (stdClass $papel): array => $this->papel($papel, $permissoes[(int) $papel->id] ?? []))->all());
    }

    public function porUuid(string $uuid): ?array
    {
        $papel = DB::table('papeis')->where('uuid', $uuid)->first(['id', 'uuid', 'nome', 'sistema']);

        return $papel === null ? null : $this->papel($papel, $this->permissoesDe([(int) $papel->id])[(int) $papel->id] ?? []);
    }

    public function nomeEmUso(string $nome, ?int $excetoId = null): bool
    {
        return DB::table('papeis')
            ->whereRaw('lower(nome) = lower(?)', [$nome])
            ->when($excetoId !== null, fn ($consulta) => $consulta->where('id', '<>', $excetoId))
            ->exists();
    }

    public function criar(int $organizacaoId, string $nome, array $permissoes): array
    {
        $uuid = (string) Str::uuid();
        $id = (int) DB::table('papeis')->insertGetId([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'nome' => $nome,
            'sistema' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->gravarPermissoes($organizacaoId, $id, $permissoes);

        return ['id' => $id, 'uuid' => $uuid, 'nome' => $nome, 'sistema' => null, 'permissoes' => $this->ordenadas($permissoes)];
    }

    public function atualizar(int $organizacaoId, int $papelId, string $nome, array $permissoes): void
    {
        DB::table('papeis')->where('id', $papelId)->update(['nome' => $nome, 'updated_at' => now()]);
        DB::table('papel_permissoes')->where('papel_id', $papelId)->delete();
        $this->gravarPermissoes($organizacaoId, $papelId, $permissoes);
    }

    public function atribuicaoDoAcesso(int $acessoId, string $uuid): ?array
    {
        $linha = DB::table('atribuicoes_papel as atribuicao')
            ->join('papeis as papel', 'papel.id', '=', 'atribuicao.papel_id')
            ->where('atribuicao.acesso_id', $acessoId)
            ->where('atribuicao.uuid', $uuid)
            ->first([
                'atribuicao.id', 'atribuicao.uuid', 'atribuicao.acesso_id', 'atribuicao.papel_id',
                'papel.uuid as papel_uuid', 'papel.sistema', 'atribuicao.escopo_tipo', 'atribuicao.escopo_id',
            ]);

        if ($linha === null) {
            return null;
        }

        return [
            'id' => (int) $linha->id,
            'uuid' => (string) $linha->uuid,
            'acesso_id' => (int) $linha->acesso_id,
            'papel_id' => (int) $linha->papel_id,
            'papel_uuid' => (string) $linha->papel_uuid,
            'sistema' => $linha->sistema === null ? null : (string) $linha->sistema,
            'escopo_tipo' => (string) $linha->escopo_tipo,
            'escopo_id' => $linha->escopo_id === null ? null : (int) $linha->escopo_id,
        ];
    }

    public function atribuicaoExiste(int $acessoId, int $papelId, string $escopoTipo, ?int $escopoId): bool
    {
        return DB::table('atribuicoes_papel')
            ->where('acesso_id', $acessoId)
            ->where('papel_id', $papelId)
            ->where('escopo_tipo', $escopoTipo)
            ->when($escopoId === null, fn ($c) => $c->whereNull('escopo_id'), fn ($c) => $c->where('escopo_id', $escopoId))
            ->exists();
    }

    public function atribuir(int $organizacaoId, int $acessoId, int $papelId, string $escopoTipo, ?int $escopoId): array
    {
        $uuid = (string) Str::uuid();
        $id = (int) DB::table('atribuicoes_papel')->insertGetId([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'acesso_id' => $acessoId,
            'papel_id' => $papelId,
            'escopo_tipo' => $escopoTipo,
            'escopo_id' => $escopoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'uuid' => $uuid];
    }

    public function removerAtribuicao(int $atribuicaoId): void
    {
        DB::table('atribuicoes_papel')->where('id', $atribuicaoId)->delete();
    }

    public function acessosAdministradores(?int $excetoAtribuicaoId = null): array
    {
        return array_values(DB::table('atribuicoes_papel as atribuicao')
            ->join('papeis as papel', 'papel.id', '=', 'atribuicao.papel_id')
            ->where('papel.sistema', 'administrador')
            ->where('atribuicao.escopo_tipo', EscopoTipo::Organizacao->value)
            ->when($excetoAtribuicaoId !== null, fn ($c) => $c->where('atribuicao.id', '<>', $excetoAtribuicaoId))
            ->distinct()
            ->pluck('atribuicao.acesso_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all());
    }

    /** @param  list<string>  $permissoes */
    private function gravarPermissoes(int $organizacaoId, int $papelId, array $permissoes): void
    {
        DB::table('papel_permissoes')->insert(array_map(static fn (string $permissao): array => [
            'organizacao_id' => $organizacaoId,
            'papel_id' => $papelId,
            'permissao' => $permissao,
        ], array_values(array_unique($permissoes))));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<string>>
     */
    private function permissoesDe(array $ids): array
    {
        $porPapel = [];
        foreach (DB::table('papel_permissoes')->whereIn('papel_id', $ids)->orderBy('permissao')->get(['papel_id', 'permissao']) as $linha) {
            $porPapel[(int) $linha->papel_id][] = (string) $linha->permissao;
        }

        return $porPapel;
    }

    /**
     * @param  list<string>  $permissoes
     * @return PapelLido
     */
    private function papel(stdClass $papel, array $permissoes): array
    {
        return [
            'id' => (int) $papel->id,
            'uuid' => (string) $papel->uuid,
            'nome' => (string) $papel->nome,
            'sistema' => $papel->sistema === null ? null : (string) $papel->sistema,
            'permissoes' => $permissoes,
        ];
    }

    /**
     * @param  list<string>  $permissoes
     * @return list<string>
     */
    private function ordenadas(array $permissoes): array
    {
        $unicas = array_values(array_unique($permissoes));
        sort($unicas);

        return $unicas;
    }
}
