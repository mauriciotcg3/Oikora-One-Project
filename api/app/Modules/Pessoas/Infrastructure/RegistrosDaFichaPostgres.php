<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Pessoas\Application\Actions\RegistrosDaFicha;
use App\Modules\Pessoas\Domain\TipoRelacionamento;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/** Pela Pessoa já localizada no alcance, na transação da requisição (contexto de RLS gravado). */
final class RegistrosDaFichaPostgres implements RegistrosDaFicha
{
    public function registrarManual(int $organizacaoId, int $pessoaId, DateTimeImmutable $ocorridoEm, string $descricao, int $autorAcessoId): array
    {
        $uuid = (string) Str::uuid();
        DB::table('eventos_linha_do_tempo')->insert([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $pessoaId,
            'tipo' => 'registro_manual',
            'ocorrido_em' => $ocorridoEm,
            'motivo_texto' => $descricao,
            'autor_acesso_id' => $autorAcessoId,
            'origem' => 'painel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['uuid' => $uuid];
    }

    public function linhaDoTempo(int $pessoaId): array
    {
        return array_values(DB::table('eventos_linha_do_tempo')
            ->where('pessoa_id', $pessoaId)
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id')
            ->get(['uuid', 'tipo', 'ocorrido_em', 'motivo_id', 'motivo_texto', 'autor_acesso_id', 'origem', 'dados'])
            ->map(static function (stdClass $linha): array {
                $dados = is_string($linha->dados) ? json_decode($linha->dados, true, flags: JSON_THROW_ON_ERROR) : null;

                return [
                    'uuid' => (string) $linha->uuid,
                    'tipo' => (string) $linha->tipo,
                    'ocorrido_em' => (string) $linha->ocorrido_em,
                    'motivo_id' => $linha->motivo_id === null ? null : (int) $linha->motivo_id,
                    'motivo_texto' => $linha->motivo_texto === null ? null : (string) $linha->motivo_texto,
                    'autor_acesso_id' => $linha->autor_acesso_id === null ? null : (int) $linha->autor_acesso_id,
                    'origem' => (string) $linha->origem,
                    'dados' => is_array($dados) ? $dados : null,
                ];
            })
            ->all());
    }

    public function relacionamentoExiste(int $pessoaId, int $relacionadaId, TipoRelacionamento $tipo): bool
    {
        return DB::table('relacionamentos')
            ->where('pessoa_id', $pessoaId)
            ->where('relacionada_id', $relacionadaId)
            ->where('tipo', $tipo->value)
            ->exists();
    }

    public function relacionar(int $organizacaoId, int $pessoaId, int $relacionadaId, TipoRelacionamento $tipo, ?string $descricao, int $autorAcessoId): array
    {
        $uuid = (string) Str::uuid();
        $par = (string) Str::uuid();
        $linha = fn (string $id, int $de, int $para, TipoRelacionamento $t): array => [
            'uuid' => $id,
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $de,
            'relacionada_id' => $para,
            'tipo' => $t->value,
            'descricao' => $descricao,
            'par_uuid' => $par,
            'criado_por_acesso_id' => $autorAcessoId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('relacionamentos')->insert([
            $linha($uuid, $pessoaId, $relacionadaId, $tipo),
            $linha((string) Str::uuid(), $relacionadaId, $pessoaId, $tipo->inverso()),
        ]);

        return ['uuid' => $uuid];
    }

    public function removerRelacionamento(int $pessoaId, string $uuid): bool
    {
        $par = DB::table('relacionamentos')->where('pessoa_id', $pessoaId)->where('uuid', $uuid)->value('par_uuid');
        if ($par === null) {
            return false;
        }

        DB::table('relacionamentos')->where('par_uuid', $par)->delete();

        return true;
    }

    public function relacionamentos(int $pessoaId): array
    {
        return array_values(DB::table('relacionamentos')
            ->where('pessoa_id', $pessoaId)
            ->orderBy('id')
            ->get(['uuid', 'tipo', 'descricao', 'relacionada_id'])
            ->map(static fn (stdClass $linha): array => [
                'uuid' => (string) $linha->uuid,
                'tipo' => (string) $linha->tipo,
                'descricao' => $linha->descricao === null ? null : (string) $linha->descricao,
                'relacionada_id' => (int) $linha->relacionada_id,
            ])
            ->all());
    }

    public function atribuirFuncao(int $organizacaoId, int $pessoaId, int $funcaoId, DateTimeImmutable $desde, ?DateTimeImmutable $ate, int $autorAcessoId): array
    {
        $uuid = (string) Str::uuid();
        DB::table('pessoa_funcoes')->insert([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $pessoaId,
            'funcao_id' => $funcaoId,
            'desde' => $desde->format('Y-m-d'),
            'ate' => $ate?->format('Y-m-d'),
            'atribuida_por_acesso_id' => $autorAcessoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['uuid' => $uuid];
    }

    public function funcao(int $pessoaId, string $uuid): ?array
    {
        $linha = DB::table('pessoa_funcoes')->where('pessoa_id', $pessoaId)->where('uuid', $uuid)->first(['id', 'desde', 'ate']);

        return $linha === null ? null : [
            'id' => (int) $linha->id,
            'desde' => (string) $linha->desde,
            'ate' => $linha->ate === null ? null : (string) $linha->ate,
        ];
    }

    public function encerrarFuncao(int $id, DateTimeImmutable $ate): void
    {
        DB::table('pessoa_funcoes')->where('id', $id)->update(['ate' => $ate->format('Y-m-d'), 'updated_at' => now()]);
    }

    public function funcoes(int $pessoaId): array
    {
        return array_values(DB::table('pessoa_funcoes')
            ->where('pessoa_id', $pessoaId)
            ->orderByDesc('desde')
            ->orderByDesc('id')
            ->get(['uuid', 'funcao_id', 'desde', 'ate'])
            ->map(static fn (stdClass $linha): array => [
                'uuid' => (string) $linha->uuid,
                'funcao_id' => (int) $linha->funcao_id,
                'desde' => (string) $linha->desde,
                'ate' => $linha->ate === null ? null : (string) $linha->ate,
            ])
            ->all());
    }

    public function tipoMembro(int $pessoaId): ?int
    {
        $id = Pessoa::query()->whereKey($pessoaId)->lockForUpdate()->value('tipo_membro_id');

        return $id === null ? null : (int) $id;
    }

    public function definirTipoMembro(int $pessoaId, ?int $tipoMembroId): void
    {
        Pessoa::query()->whereKey($pessoaId)->firstOrFail()->forceFill(['tipo_membro_id' => $tipoMembroId])->save();
    }
}
