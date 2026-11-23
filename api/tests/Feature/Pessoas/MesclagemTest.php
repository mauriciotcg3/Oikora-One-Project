<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Shared\Cifra\CampoSensivelEncrypter;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** Possíveis duplicados e mesclagem (P FR-016; RN-11, RN-23; U-27, U-30; CA-21; T-32). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Mesclagem');
    $this->x = criarIgreja($this->org, 'Igreja X');
    $this->y = criarIgreja($this->org, 'Igreja Y');
});

function db(): Connection
{
    return DB::connection('pgsql_dono');
}

function mesclar(string $mantida, string $absorvida, array $valores = []): TestResponse
{
    return test()->postJson('/api/v1/painel/mesclagens', ['mantida' => $mantida, 'absorvida' => $absorvida, 'valores' => $valores]);
}

it('lista pares pela RN-23, sem a lixeira e sem os marcados como não duplicados', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar]);
    $joao1 = criarPessoa($this->org, $this->x, ['nome' => 'João da Silva', 'nascimento' => '1980-01-01']);
    $joao2 = criarPessoa($this->org, $this->y, ['nome' => 'Joao da Silva', 'nascimento' => '1980-01-01']);
    $mariaA = criarPessoa($this->org, $this->x, ['nome' => 'Maria A']);
    $mariaB = criarPessoa($this->org, $this->x, ['nome' => 'Maria B']);
    criarContato($this->org, $mariaA, 'email', 'maria@exemplo.test');
    criarContato($this->org, $mariaB, 'email', 'MARIA@exemplo.test');
    $lixo = criarPessoa($this->org, $this->x, ['nome' => 'João da Silva', 'nascimento' => '1980-01-01', 'excluida_em' => now()]);
    $ana1 = criarPessoa($this->org, $this->x, ['nome' => 'Ana', 'nascimento' => '1990-02-02']);
    $ana2 = criarPessoa($this->org, $this->x, ['nome' => 'Ana', 'nascimento' => '1990-02-02']);

    $this->postJson('/api/v1/painel/duplicados/nao-duplicados', ['pessoa' => uuidDe('pessoas', $ana1), 'outra' => uuidDe('pessoas', $ana2)])->assertNoContent();

    $resposta = $this->getJson('/api/v1/painel/duplicados')->assertOk();
    $pares = collect($resposta->json('data'))->map(fn (array $par): array => [
        collect($par['pessoas'])->pluck('uuid')->sort()->values()->all(), $par['criterios'],
    ])->all();

    $esperado = fn (int $a, int $b): array => collect([uuidDe('pessoas', $a), uuidDe('pessoas', $b)])->sort()->values()->all();
    expect($pares)->toContain([$esperado($joao1, $joao2), ['nome_nascimento']])
        ->and($pares)->toContain([$esperado($mariaA, $mariaB), ['email']])
        ->and($resposta->json('meta.total'))->toBe(2)
        ->and(json_encode($resposta->json()))->not->toContain(uuidDe('pessoas', $lixo));
});

it('mescla com os valores escolhidos e move contatos, relacionamentos, funções e eventos', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar]);
    $mantida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Carlos', 'profissao' => 'Pedreiro', 'nascimento' => '1970-05-05']);
    $absorvida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Carlos Souza', 'profissao' => 'Engenheiro', 'nascimento' => '1970-05-05']);
    $outra = criarPessoa($this->org, $this->x, ['nome' => 'Filha']);
    $contato = criarContato($this->org, $absorvida, 'celular', '+5511911112222');
    inserirComoDono('relacionamentos', ['organizacao_id' => $this->org, 'pessoa_id' => $absorvida, 'relacionada_id' => $outra, 'tipo' => 'pai_mae', 'par_uuid' => (string) Str::uuid()]);
    $funcao = inserirComoDono('listas_valores', ['organizacao_id' => $this->org, 'lista' => 'funcao', 'nome' => 'Diácono', 'ativo' => true, 'ordem' => 1]);
    inserirComoDono('pessoa_funcoes', ['organizacao_id' => $this->org, 'pessoa_id' => $absorvida, 'funcao_id' => $funcao, 'desde' => '2020-01-01']);
    inserirComoDono('eventos_linha_do_tempo', ['organizacao_id' => $this->org, 'pessoa_id' => $absorvida, 'tipo' => 'registro_manual', 'ocorrido_em' => now(), 'origem' => 'painel']);

    mesclar(uuidDe('pessoas', $mantida), uuidDe('pessoas', $absorvida), ['nome' => 'absorvida', 'profissao' => 'mantida'])
        ->assertCreated()->assertJsonStructure(['operacao', 'expira_em']);

    $m = db()->table('pessoas')->find($mantida);
    $a = db()->table('pessoas')->find($absorvida);
    expect($m->nome)->toBe('Carlos Souza')
        ->and($m->profissao)->toBe('Pedreiro')
        ->and($a->excluida_em)->not->toBeNull()
        ->and($a->exclusao_motivo)->toBe('mesclagem')
        ->and(db()->table('pessoa_contatos')->where('id', $contato)->value('pessoa_id'))->toBe($mantida)
        ->and(db()->table('relacionamentos')->where('relacionada_id', $outra)->value('pessoa_id'))->toBe($mantida)
        ->and(db()->table('pessoa_funcoes')->where('pessoa_id', $absorvida)->count())->toBe(0)
        ->and(db()->table('pessoa_funcoes')->where('pessoa_id', $mantida)->count())->toBe(1)
        ->and(db()->table('eventos_linha_do_tempo')->where('pessoa_id', $mantida)->where('tipo', 'registro_manual')->count())->toBe(1);
});

it('grava o snapshot cifrado com validade de 90 dias', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar]);
    $mantida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Pedro']);
    $absorvida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Pedro Henrique', 'cpf' => '529.982.247-25']);

    $uuid = mesclar(uuidDe('pessoas', $mantida), uuidDe('pessoas', $absorvida))->assertCreated()->json('operacao');

    $operacao = db()->table('operacoes_desfaziveis')->where('uuid', $uuid)->first();
    expect($operacao->tipo)->toBe('mesclagem')
        ->and($operacao->estado)->toBe('executada')
        ->and($operacao->snapshot)->not->toContain('Pedro Henrique')
        ->and(now()->diffInDays($operacao->expira_em, true))->toBeGreaterThan(89.9)->toBeLessThan(90.1);

    $snapshot = json_decode(app(CampoSensivelEncrypter::class)->decryptString($operacao->snapshot), true);
    expect($snapshot['absorvida']['nome'])->toBe('Pedro Henrique')
        ->and($snapshot['absorvida']['cpf_hash'])->not->toBeNull();
});

it('recusa quando as duas pessoas têm conta', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar]);
    $mantida = criarPessoa($this->org, $this->x, ['nome' => 'Com conta 1']);
    $absorvida = criarPessoa($this->org, $this->x, ['nome' => 'Com conta 2']);
    criarAcesso($this->org, criarConta(['email' => 'c1@exemplo.test', 'celular' => '+5511900000001']), $mantida);
    criarAcesso($this->org, criarConta(['email' => 'c2@exemplo.test', 'celular' => '+5511900000002']), $absorvida);

    mesclar(uuidDe('pessoas', $mantida), uuidDe('pessoas', $absorvida))
        ->assertUnprocessable()->assertJson(['codigo' => 'mesclagem_contas_conflitantes']);
    expect(db()->table('pessoas')->find($absorvida)->excluida_em)->toBeNull();
});

it('move o acesso quando só a absorvida tem conta', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar]);
    $mantida = criarPessoa($this->org, $this->x, ['nome' => 'Sem conta']);
    $absorvida = criarPessoa($this->org, $this->x, ['nome' => 'Com conta']);
    $acesso = criarAcesso($this->org, criarConta(['email' => 'c3@exemplo.test', 'celular' => '+5511900000003']), $absorvida);

    mesclar(uuidDe('pessoas', $mantida), uuidDe('pessoas', $absorvida))->assertCreated();

    expect(db()->table('acessos')->where('id', $acesso)->value('pessoa_id'))->toBe($mantida);
});

it('sem pessoas.mesclar na igreja de uma das duas → 403', function (): void {
    $ator = entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar], 'igreja', $this->x);
    $papelVer = criarPapel($this->org, 'Só ver Y', [Permissao::PessoasVer]);
    atribuirPapel($this->org, $ator['acesso'], $papelVer, 'igreja', $this->y);
    $daX = criarPessoa($this->org, $this->x, ['nome' => 'Da X']);
    $daY = criarPessoa($this->org, $this->y, ['nome' => 'Da Y']);

    mesclar(uuidDe('pessoas', $daX), uuidDe('pessoas', $daY))->assertForbidden();
    expect(db()->table('pessoas')->find($daY)->excluida_em)->toBeNull();
});

it('grava o evento mesclagem nas duas e a auditoria só com nomes de campo', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar]);
    $mantida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Lúcia']);
    $absorvida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Lúcia Ramos', 'profissao' => 'Professora']);

    mesclar(uuidDe('pessoas', $mantida), uuidDe('pessoas', $absorvida), ['profissao' => 'absorvida'])->assertCreated();

    expect(db()->table('eventos_linha_do_tempo')->where('tipo', 'mesclagem')->pluck('pessoa_id')->sort()->values()->all())
        ->toBe(collect([$mantida, $absorvida])->sort()->values()->all());
    $registro = auditoria('pessoa.mesclada')[0];
    expect(json_decode($registro->campos, true))->toBe(['profissao'])
        ->and(json_encode($registro->metadados))->not->toContain('Professora')
        ->and(json_encode($registro->metadados))->not->toContain('Lúcia');
});

it('limpa CPF, hash e matrícula da absorvida e leva o CPF escolhido sem violar a unicidade', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar, Permissao::DocumentosEditar, Permissao::DocumentosVer]);
    $mantida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Rui']);
    $absorvida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Rui Barbosa', 'cpf' => '529.982.247-25', 'matricula' => 'M-001']);
    $hash = db()->table('pessoas')->where('id', $absorvida)->value('cpf_hash');

    mesclar(uuidDe('pessoas', $mantida), uuidDe('pessoas', $absorvida), ['cpf' => 'absorvida'])->assertCreated();

    $a = db()->table('pessoas')->find($absorvida);
    expect($a->cpf)->toBeNull()
        ->and($a->cpf_hash)->toBeNull()
        ->and($a->matricula)->toBeNull()
        ->and(db()->table('pessoas')->where('id', $mantida)->value('cpf_hash'))->toBe($hash);
});

it('sem documentos.editar não leva o CPF da absorvida', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasMesclar]);
    $mantida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Ivo']);
    $absorvida = criarPessoaComFicha($this->org, $this->x, ['nome' => 'Ivo', 'cpf' => '529.982.247-25']);

    mesclar(uuidDe('pessoas', $mantida), uuidDe('pessoas', $absorvida), ['cpf' => 'absorvida'])
        ->assertUnprocessable()->assertJsonValidationErrors(['valores.cpf']);
});
