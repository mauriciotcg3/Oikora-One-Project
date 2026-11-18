<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** OIK-SPEC-0001 T-46 — consulta da auditoria (CA-12; RN-15; OIK-ADR-0005 §3). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Auditoria');
    $this->sede = criarIgreja($this->org, 'Sede');
    $this->filial = criarIgreja($this->org, 'Filial');
});

/** Grava uma linha como o dono do schema, com o momento escolhido. */
function linhaDeAuditoria(int $organizacaoId, string $evento, array $dados = []): void
{
    DB::connection('pgsql_dono')->table('auditoria')->insert([
        'organizacao_id' => $organizacaoId,
        'ocorrido_em' => now(),
        'evento' => $evento,
        'alvo_tipo' => 'pessoa',
        'alvo_id' => 'alvo-1',
        'campos' => json_encode([]),
        'metadados' => json_encode((object) []),
        'canal' => 'painel',
        ...$dados,
    ]);
}

const SECRETARIA_PRONTA = [
    Permissao::PessoasVer, Permissao::PessoasCriar, Permissao::PessoasEditar,
    Permissao::DocumentosVer, Permissao::ContatosVer, Permissao::FeVer,
];

it('recusa a consulta sem auditoria.ver, como a Secretaria pronta', function (): void {
    entrarNoPainel($this->org, $this->sede, SECRETARIA_PRONTA, sistema: 'secretaria');

    $this->getJson('/api/v1/painel/auditoria')->assertForbidden()->assertJsonPath('codigo', 'authz.denied');
});

it('pagina do mais recente para o mais antigo', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::AuditoriaVer]);
    // O próprio login pelo painel audita a elevação: começa do zero para contar só o que o teste grava.
    DB::connection('pgsql_dono')->table('auditoria')->where('organizacao_id', $this->org)->delete();
    foreach (range(1, 30) as $minutos) {
        linhaDeAuditoria($this->org, 'pessoa.criada', ['ocorrido_em' => now()->subMinutes($minutos), 'alvo_id' => "p-{$minutos}"]);
    }

    $primeira = $this->getJson('/api/v1/painel/auditoria')->assertOk()
        ->assertJsonPath('meta.total', 30)
        ->json();
    expect($primeira['data'])->toHaveCount(25);

    $datas = array_column($primeira['data'], 'ocorrido_em');
    $ordenadas = $datas;
    rsort($ordenadas);
    expect($datas)->toBe($ordenadas);

    $this->getJson('/api/v1/painel/auditoria?pagina=2&por_pagina=25')->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.alvo.id', 'p-26');
    $this->getJson('/api/v1/painel/auditoria?por_pagina=10')->assertUnprocessable();
});

it('restringe por período, evento, ator e alvo', function (): void {
    $ator = entrarNoPainel($this->org, $this->sede, [Permissao::AuditoriaVer]);
    DB::connection('pgsql_dono')->table('auditoria')->where('organizacao_id', $this->org)->delete();
    $outroAcesso = criarAcesso($this->org, criarContaVerificada(['email' => 'outro@exemplo.test', 'celular' => '+5511900001111']), criarPessoa($this->org, $this->sede));

    $hoje = Carbon::now('America/Sao_Paulo')->setTime(12, 0);
    linhaDeAuditoria($this->org, 'pessoa.criada', ['ocorrido_em' => $hoje, 'acesso_id' => $ator['acesso'], 'alvo_id' => 'p-1']);
    linhaDeAuditoria($this->org, 'pessoa.atualizada', ['ocorrido_em' => $hoje->copy()->subMinute(), 'acesso_id' => $outroAcesso, 'alvo_id' => 'p-2']);
    linhaDeAuditoria($this->org, 'autorizacao.papel_criado', ['ocorrido_em' => $hoje->copy()->addDays(2), 'acesso_id' => $ator['acesso'], 'alvo_tipo' => 'papel', 'alvo_id' => 'papel-1']);

    $dia = static fn (Carbon $data): string => $data->format('Y-m-d');
    $alvos = fn (string $consulta): array => array_column($this->getJson("/api/v1/painel/auditoria?{$consulta}")->assertOk()->json('data.*.alvo'), 'id');

    expect($alvos('de='.$dia($hoje).'&ate='.$dia($hoje)))->toBe(['p-1', 'p-2'])
        ->and($alvos('de='.$dia($hoje->copy()->addDay())))->toBe(['papel-1'])
        ->and($alvos('evento=pessoa.atualizada'))->toBe(['p-2'])
        ->and($alvos('ator='.uuidDe('acessos', $ator['acesso'])))->toBe(['papel-1', 'p-1'])
        ->and($alvos('alvo_tipo=papel&alvo=papel-1'))->toBe(['papel-1'])
        ->and($alvos('ator=00000000-0000-4000-8000-000000000000'))->toBe([]);

    $this->getJson('/api/v1/painel/auditoria?evento=nao.existe')->assertUnprocessable();
});

it('com auditoria.ver só numa igreja, não mostra eventos de outra igreja nem os de governança', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::AuditoriaVer], 'igreja', $this->sede);
    DB::connection('pgsql_dono')->table('auditoria')->where('organizacao_id', $this->org)->delete();

    linhaDeAuditoria($this->org, 'pessoa.criada', ['igreja_id' => $this->sede, 'alvo_id' => 'da-sede']);
    linhaDeAuditoria($this->org, 'pessoa.criada', ['igreja_id' => $this->filial, 'alvo_id' => 'da-filial']);
    linhaDeAuditoria($this->org, 'autorizacao.papel_criado', ['igreja_id' => null, 'alvo_tipo' => 'papel', 'alvo_id' => 'governanca']);

    $this->getJson('/api/v1/painel/auditoria')->assertOk()
        ->assertJsonPath('data.*.alvo.id', ['da-sede'])
        ->assertJsonPath('data.0.igreja.nome', 'Sede')
        ->assertJsonPath('meta.total', 1);
});

it('devolve nomes de campos e metadados codificados, nunca valores', function (): void {
    $ator = entrarNoPainel($this->org, $this->sede, [Permissao::AuditoriaVer]);
    DB::connection('pgsql_dono')->table('auditoria')->where('organizacao_id', $this->org)->delete();
    linhaDeAuditoria($this->org, 'pessoas.obrigatorios_atualizados', [
        'acesso_id' => $ator['acesso'],
        'alvo_tipo' => 'campos_obrigatorios',
        'alvo_id' => 'membro',
        'campos' => json_encode(['nascimento', 'cep']),
        'metadados' => json_encode(['vinculo' => 'membro', 'campos_antes' => [], 'campos_depois' => ['cep', 'nascimento']]),
        'request_id' => 'req-1',
    ]);

    $linha = $this->getJson('/api/v1/painel/auditoria')->assertOk()->json('data.0');

    expect(array_keys($linha))->toBe(['ocorrido_em', 'evento', 'ator', 'alvo', 'igreja', 'campos', 'metadados', 'canal', 'request_id'])
        ->and($linha['campos'])->toBe(['nascimento', 'cep'])
        ->and($linha['metadados'])->toBe(['vinculo' => 'membro', 'campos_antes' => [], 'campos_depois' => ['cep', 'nascimento']])
        ->and($linha['ator'])->toBe(['acesso' => uuidDe('acessos', $ator['acesso']), 'plataforma' => false]);
});

it('mostra só eventos da organização ativa (RLS)', function (): void {
    $outra = criarOrganizacao('Outra');
    linhaDeAuditoria($outra, 'pessoa.criada', ['alvo_id' => 'de-fora']);
    entrarNoPainel($this->org, $this->sede, [Permissao::AuditoriaVer]);
    linhaDeAuditoria($this->org, 'pessoa.criada', ['alvo_id' => 'de-dentro']);

    $alvos = $this->getJson('/api/v1/painel/auditoria?evento=pessoa.criada')->assertOk()->json('data.*.alvo.id');

    expect($alvos)->toBe(['de-dentro']);
});
