<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Rls');

/** Cria a organização pelo `oikora_dono`, fora da RLS, e devolve o id. */
function criarOrganizacao(string $nome = 'Igreja Teste'): int
{
    return (int) DB::connection('pgsql_dono')->table('organizacoes')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'nome' => $nome,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Cria a conta pelo `oikora_dono`, como faria o aceite de convite (T-22), e devolve o id. */
function criarConta(array $dados = []): int
{
    return (int) DB::connection('pgsql_dono')->table('contas')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'email' => 'maria@exemplo.test',
        'celular' => '+5511987654321',
        'password' => Hash::make('senha-correta-123'),
        'estado' => 'ativa',
        'created_at' => now(),
        'updated_at' => now(),
        ...$dados,
    ]);
}

/** Liga uma conta a uma organização e devolve o id do acesso. */
function criarAcesso(int $organizacaoId, int $contaId, int $pessoaId, string $estado = 'ativo'): int
{
    return (int) DB::connection('pgsql_dono')->table('acessos')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $organizacaoId,
        'conta_id' => $contaId,
        'pessoa_id' => $pessoaId,
        'estado' => $estado,
        'origem' => 'convite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Roda `$acao` numa transação do `oikora_app` com o contexto gravado (OIK-ADR-0011 §3). */
function noContexto(?int $organizacaoId, ?int $contaId, Closure $acao): mixed
{
    return DB::transaction(function () use ($organizacaoId, $contaId, $acao): mixed {
        if ($organizacaoId !== null) {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
        }
        if ($contaId !== null) {
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);
        }

        return $acao();
    });
}
