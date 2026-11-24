<?php

use App\Modules\Auditoria\Contracts\OperacoesDesfaziveis;
use App\Modules\Pessoas\Application\Lixeira\ExclusaoBloqueada;
use App\Modules\Pessoas\Application\Lixeira\RemovedorDePessoas;
use Illuminate\Support\Facades\DB;

/** Removedor de Pessoas, modos anonimizar e apagar (OIK-ADR-0005 §8; RN-19; T-35). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Removedor');
    $this->igreja = criarIgreja($this->org, 'Sede');
    $this->pessoa = criarPessoaComFicha($this->org, $this->igreja, [
        'nome' => 'Helena Prado',
        'cpf' => '529.982.247-25',
        'rg' => '12.345.678-9',
        'tipo_sanguineo' => 'O+',
        'necessidades_especiais' => 'Baixa visão',
        'foto_path' => 'fotos/helena.jpg',
        'profissao' => 'Advogada',
        'vinculo' => 'membro',
        'situacao' => 'ativa',
        'entrada_em' => '2015-03-01',
        'matricula' => 'M-77',
        'excluida_em' => now()->subDays(40),
        'exclusao_motivo' => 'pedido da pessoa',
    ]);
    criarContato($this->org, $this->pessoa, 'email', 'helena@exemplo.test');
    inserirComoDono('pessoa_enderecos', ['organizacao_id' => $this->org, 'pessoa_id' => $this->pessoa, 'cidade' => 'Recife']);
    $funcao = inserirComoDono('listas_valores', ['organizacao_id' => $this->org, 'lista' => 'funcao', 'nome' => 'Professora', 'ativo' => true, 'ordem' => 1]);
    inserirComoDono('pessoa_funcoes', ['organizacao_id' => $this->org, 'pessoa_id' => $this->pessoa, 'funcao_id' => $funcao, 'desde' => '2020-01-01']);
    inserirComoDono('eventos_linha_do_tempo', ['organizacao_id' => $this->org, 'pessoa_id' => $this->pessoa, 'tipo' => 'registro_manual', 'ocorrido_em' => now(), 'origem' => 'painel', 'motivo_texto' => 'Conversa pastoral']);
    $outra = criarPessoa($this->org, $this->igreja);
    $this->operacao = noContexto($this->org, null, fn (): array => app(OperacoesDesfaziveis::class)
        ->registrar($this->org, 'mesclagem', null, ['nome' => 'Helena Prado'], ['mantida_id' => $this->pessoa, 'absorvida_id' => $outra]));
});

function remover(int $organizacaoId, int $pessoaId): string
{
    return noContexto($organizacaoId, null, fn (): string => app(RemovedorDePessoas::class)->remover($pessoaId, 'teste'));
}

it('anonimizar apaga contatos, documentos, saúde, endereço, foto e textos livres, e mantém vínculo e datas', function (): void {
    config(['lgpd.modo_exclusao' => 'anonimizar']);

    expect(remover($this->org, $this->pessoa))->toBe('anonimizar');

    $ficha = DB::connection('pgsql_dono')->table('pessoas')->find($this->pessoa);
    expect($ficha->nome)->toBe('Pessoa removida')
        ->and([$ficha->cpf, $ficha->cpf_hash, $ficha->rg, $ficha->tipo_sanguineo, $ficha->necessidades_especiais, $ficha->foto_path, $ficha->profissao, $ficha->exclusao_motivo, $ficha->matricula])
        ->each->toBeNull()
        ->and($ficha->vinculo)->toBe('membro')
        ->and($ficha->situacao)->toBe('ativa')
        ->and($ficha->entrada_em)->toBe('2015-03-01')
        ->and(DB::connection('pgsql_dono')->table('pessoa_contatos')->where('pessoa_id', $this->pessoa)->count())->toBe(0)
        ->and(DB::connection('pgsql_dono')->table('pessoa_enderecos')->where('pessoa_id', $this->pessoa)->count())->toBe(0)
        ->and(DB::connection('pgsql_dono')->table('eventos_linha_do_tempo')->where('pessoa_id', $this->pessoa)->value('motivo_texto'))->toBeNull();
});

it('apagar remove a pessoa e o que só existe por ela; os dois modos apagam snapshots e registram a exclusão', function (string $modo): void {
    config(['lgpd.modo_exclusao' => $modo]);
    $uuid = uuidDe('pessoas', $this->pessoa);

    expect(remover($this->org, $this->pessoa))->toBe($modo);

    $dono = DB::connection('pgsql_dono');
    $operacao = $dono->table('operacoes_desfaziveis')->find($this->operacao['id']);
    expect($operacao->snapshot)->toBeNull()
        ->and($operacao->estado)->toBe('expirada');

    $registro = $dono->table('exclusoes_executadas')->where('pessoa_id', $this->pessoa)->sole();
    expect($registro->modo)->toBe($modo)
        ->and($registro->pessoa_uuid)->toBe($uuid)
        ->and($registro->organizacao_id)->toBe($this->org);

    if ($modo === 'apagar') {
        expect($dono->table('pessoas')->find($this->pessoa))->toBeNull()
            ->and($dono->table('pessoa_funcoes')->where('pessoa_id', $this->pessoa)->count())->toBe(0)
            ->and($dono->table('eventos_linha_do_tempo')->where('pessoa_id', $this->pessoa)->count())->toBe(0)
            ->and($dono->table('pessoa_contatos')->where('pessoa_id', $this->pessoa)->count())->toBe(0);
    }
})->with(['anonimizar', 'apagar']);

it('apagar uma pessoa que teve acesso revoga o acesso e anonimiza', function (): void {
    config(['lgpd.modo_exclusao' => 'apagar']);
    $acesso = criarAcesso($this->org, criarConta(['email' => 'helena.conta@exemplo.test', 'celular' => '+5511977776666']), $this->pessoa);

    expect(remover($this->org, $this->pessoa))->toBe('anonimizar');
    expect(DB::connection('pgsql_dono')->table('acessos')->find($acesso)->estado)->toBe('revogado')
        ->and(DB::connection('pgsql_dono')->table('pessoas')->find($this->pessoa)->nome)->toBe('Pessoa removida');
});

it('em produção, sem lgpd.modo_exclusao, recusa com lgpd.erasure.blocked_no_policy', function (): void {
    config(['lgpd.modo_exclusao' => null]);
    app()->detectEnvironment(fn (): string => 'production');
    capturarLog();

    try {
        expect(fn () => remover($this->org, $this->pessoa))->toThrow(ExclusaoBloqueada::class, 'lgpd.erasure.blocked_no_policy');
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }

    expect(conteudoDoLog())->toContain('lgpd.erasure.blocked_no_policy')
        ->and(DB::connection('pgsql_dono')->table('pessoas')->find($this->pessoa)->nome)->toBe('Helena Prado')
        ->and(DB::connection('pgsql_dono')->table('exclusoes_executadas')->count())->toBe(0);
});
