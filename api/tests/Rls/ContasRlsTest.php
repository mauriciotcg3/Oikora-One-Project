<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function criarPessoaParaAcesso(int $organizacaoId): int
{
    $igrejaId = (int) DB::connection('pgsql_dono')->table('igrejas')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $organizacaoId,
        'nome' => 'Igreja do acesso',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) DB::connection('pgsql_dono')->table('pessoas')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $organizacaoId,
        'igreja_id' => $igrejaId,
        'nome' => 'Pessoa do acesso',
        'nome_busca' => 'pessoa do acesso',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function (): void {
    $this->orgA = criarOrganizacao('A');
    $this->orgB = criarOrganizacao('B');
    $this->contaDeA = criarConta(['email' => 'a@exemplo.test', 'celular' => '+5511900000001']);
    $this->contaDeB = criarConta(['email' => 'b@exemplo.test', 'celular' => '+5511900000002']);
    $this->pessoaDeA = criarPessoaParaAcesso($this->orgA);
    $this->pessoaDeB = criarPessoaParaAcesso($this->orgB);
    criarAcesso($this->orgA, $this->contaDeA, $this->pessoaDeA);
    criarAcesso($this->orgB, $this->contaDeB, $this->pessoaDeB);
});

it('não mostra conta alguma sem contexto', function (): void {
    expect(DB::table('contas')->count())->toBe(0);
});

it('mostra só as contas com acesso ativo na organização do contexto', function (): void {
    $ids = noContexto($this->orgA, null, fn () => DB::table('contas')->pluck('id')->all());

    expect($ids)->toBe([$this->contaDeA]);
});

it('esconde a conta cujo acesso foi revogado', function (): void {
    $revogada = criarConta(['email' => 'r@exemplo.test', 'celular' => '+5511900000003']);
    criarAcesso($this->orgA, $revogada, criarPessoaParaAcesso($this->orgA), estado: 'revogado');

    $ids = noContexto($this->orgA, null, fn () => DB::table('contas')->pluck('id')->all());

    expect($ids)->not->toContain($revogada);
});

it('sempre mostra a própria conta, mesmo sem acesso na organização', function (): void {
    $ids = noContexto($this->orgA, $this->contaDeB, fn () => DB::table('contas')->orderBy('id')->pluck('id')->all());

    expect($ids)->toBe([$this->contaDeA, $this->contaDeB]);
});

it('localiza a conta sem contexto, devolvendo só id, hash, estado e canal de 2FA', function (string $identificador): void {
    $linha = DB::selectOne('select * from identidade_localizar_conta(?)', [$identificador]);

    expect(array_keys((array) $linha))->toEqualCanonicalizing(['id', 'password', 'estado', 'dois_fatores_canal'])
        ->and($linha->id)->toBe($this->contaDeA);
})->with(['a@exemplo.test', '+5511900000001']);

it('informa se um identificador está em uso sem devolver a conta', function (): void {
    expect(DB::scalar('select identidade_identificador_em_uso(?)', ['a@exemplo.test']))->toBeTrue()
        ->and(DB::scalar('select identidade_identificador_em_uso(?)', ['livre@exemplo.test']))->toBeFalse();
});

it('impede oikora_app de alterar ou excluir eventos_autenticacao', function (string $sql): void {
    DB::table('eventos_autenticacao')->insert([
        'identificador_hash' => 'h', 'evento' => 'auth.login.falha', 'ip_hash' => 'i', 'ocorrido_em' => now(),
    ]);

    DB::statement($sql);
})->with([
    'update' => "update eventos_autenticacao set evento = 'x'",
    'delete' => 'delete from eventos_autenticacao',
])->throws(QueryException::class, 'permission denied');

it('recusa segundo acesso ativo da mesma conta na organização', function (): void {
    criarAcesso($this->orgA, $this->contaDeA, criarPessoaParaAcesso($this->orgA));
})->throws(QueryException::class, 'unique');

it('recusa segundo acesso ativo da mesma pessoa na organização', function (): void {
    criarAcesso($this->orgA, $this->contaDeB, $this->pessoaDeA);
})->throws(QueryException::class, 'unique');

it('permite novo acesso depois de revogar o anterior', function (): void {
    DB::connection('pgsql_dono')->table('acessos')->where('conta_id', $this->contaDeA)->update(['estado' => 'revogado']);

    expect(criarAcesso($this->orgA, $this->contaDeA, $this->pessoaDeA))->toBeGreaterThan(0);
});
