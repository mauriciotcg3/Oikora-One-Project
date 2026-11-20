<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** OIK-SPEC-0001 T-47, caso 7: RLS e FKs compostas isolam as quatro tabelas da OIK-ADR-0012. */
function concessaoNaOrganizacao(int $org): array
{
    $igreja = criarIgreja($org, 'Sede');
    $acesso = criarAcesso($org, criarConta(['email' => Str::lower(Str::random(8)).'@exemplo.test', 'celular' => sprintf('+55119%08d', random_int(0, 99999999))]), criarPessoa($org, $igreja));
    $solicitacao = inserirComoDono('solicitacoes_permissao', [
        'organizacao_id' => $org, 'solicitante_acesso_id' => $acesso, 'justificativa' => 'x', 'duracao_horas' => 2,
    ]);
    DB::connection('pgsql_dono')->table('solicitacao_permissoes')->insert([
        'organizacao_id' => $org, 'solicitacao_id' => $solicitacao, 'permissao' => 'pessoas.ver', 'escopo_tipo' => 'organizacao',
    ]);
    $concessao = inserirComoDono('concessoes_permissao', [
        'organizacao_id' => $org, 'solicitacao_id' => $solicitacao, 'acesso_id' => $acesso, 'inicia_em' => now(), 'expira_em' => now()->addHours(2),
    ]);
    DB::connection('pgsql_dono')->table('concessao_permissoes')->insert([
        'organizacao_id' => $org, 'concessao_id' => $concessao, 'permissao' => 'pessoas.ver', 'escopo_tipo' => 'organizacao',
    ]);

    return ['solicitacao' => $solicitacao, 'concessao' => $concessao, 'acesso' => $acesso];
}

const TABELAS_CONCESSAO = ['solicitacoes_permissao', 'solicitacao_permissoes', 'concessoes_permissao', 'concessao_permissoes'];

it('cada organização vê só as próprias linhas nas quatro tabelas', function (): void {
    $orgA = criarOrganizacao('A');
    $orgB = criarOrganizacao('B');
    concessaoNaOrganizacao($orgA);
    concessaoNaOrganizacao($orgB);

    foreach (TABELAS_CONCESSAO as $tabela) {
        $organizacoes = noContexto($orgA, null, fn () => DB::table($tabela)->pluck('organizacao_id')->unique()->values()->all());
        expect(array_map('intval', $organizacoes))->toBe([$orgA], $tabela);
    }
});

it('a FK composta impede ligar item ou concessão a pai de outra organização', function (): void {
    $orgA = criarOrganizacao('A');
    $orgB = criarOrganizacao('B');
    $deA = concessaoNaOrganizacao($orgA);

    expect(fn () => DB::connection('pgsql_dono')->table('concessao_permissoes')->insert([
        'organizacao_id' => $orgB, 'concessao_id' => $deA['concessao'], 'permissao' => 'pessoas.ver', 'escopo_tipo' => 'organizacao',
    ]))->toThrow(QueryException::class);

    expect(fn () => inserirComoDono('concessoes_permissao', [
        'organizacao_id' => $orgB, 'solicitacao_id' => $deA['solicitacao'], 'acesso_id' => $deA['acesso'], 'inicia_em' => now(), 'expira_em' => now()->addHour(),
    ]))->toThrow(QueryException::class);
});

it('o banco recusa concessão acima de 72 horas, permissão não solicitável e segunda concessão do mesmo pedido', function (): void {
    $org = criarOrganizacao();
    $base = concessaoNaOrganizacao($org);

    expect(fn () => inserirComoDono('concessoes_permissao', [
        'organizacao_id' => $org, 'solicitacao_id' => $base['solicitacao'], 'acesso_id' => $base['acesso'], 'inicia_em' => now(), 'expira_em' => now()->addHour(),
    ]))->toThrow(QueryException::class, 'concessoes_permissao_organizacao_id_solicitacao_id_unique');

    $outra = inserirComoDono('solicitacoes_permissao', ['organizacao_id' => $org, 'solicitante_acesso_id' => $base['acesso'], 'justificativa' => 'x', 'duracao_horas' => 2]);
    expect(fn () => inserirComoDono('concessoes_permissao', [
        'organizacao_id' => $org, 'solicitacao_id' => $outra, 'acesso_id' => $base['acesso'], 'inicia_em' => now(), 'expira_em' => now()->addHours(73),
    ]))->toThrow(QueryException::class, 'concessoes_permissao_ate_72h');

    expect(fn () => DB::connection('pgsql_dono')->table('solicitacao_permissoes')->insert([
        'organizacao_id' => $org, 'solicitacao_id' => $outra, 'permissao' => 'papeis.gerir', 'escopo_tipo' => 'organizacao',
    ]))->toThrow(QueryException::class, 'solicitacao_permissoes_solicitavel');
});
