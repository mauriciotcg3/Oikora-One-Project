<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

const SOLICITACOES = '/api/v1/painel/solicitacoes-permissao';

/** Igreja A e B; a secretaria só vê pessoas da A. O administrador tem tudo na organização. */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Concessões');
    $this->igrejaA = criarIgreja($this->org, 'Igreja A');
    $this->igrejaB = criarIgreja($this->org, 'Igreja B');
    $this->admin = entrarNoPainel($this->org, $this->igrejaA, todasAsPermissoes(), sistema: 'administrador');
    $this->secretaria = entrarNoPainel($this->org, $this->igrejaA, [Permissao::PessoasVer], 'igreja', $this->igrejaA);
});

function como(array $ator): void
{
    comSessao($ator['cookie']);
}

/** Administrador (papel de sistema) cujo papel só tem a aprovação e `pessoas.ver`. */
function aprovadorLimitado(int $org, int $igreja): array
{
    $conta = criarContaVerificada(['email' => 'limitado@exemplo.test', 'celular' => '+5511912340000']);
    $acesso = criarAcesso($org, $conta, criarPessoa($org, $igreja, ['nome' => 'Limitado']));
    atribuirPapel($org, $acesso, criarPapel($org, 'Administrador restrito', [Permissao::SolicitacoesPermissaoAprovar, Permissao::PessoasVer], 'administrador'));
    novoNavegador();
    $cookie = cookieDaSessao(test()->postJson(ROTA_LOGIN, ['identificador' => 'limitado@exemplo.test', 'senha' => SENHA, 'entrada' => 'painel'])->assertNoContent());
    comSessao($cookie);

    return ['acesso' => $acesso, 'cookie' => $cookie];
}

function pedido(array $mudancas = []): array
{
    return [
        'permissoes' => [['chave' => 'pessoas.criar', 'escopo_tipo' => 'igreja', 'escopo_id' => uuidDe('igrejas', test()->igrejaA)]],
        'duracao_horas' => 2,
        'justificativa' => 'Mutirão de cadastro no sábado',
        ...$mudancas,
    ];
}

function pedir(array $mudancas = []): string
{
    return test()->postJson(SOLICITACOES, pedido($mudancas))->assertCreated()->json('data.uuid');
}

it('aceita pedido elegível de 1 a 72 horas e recusa governança, exportação e duração fora da faixa', function (): void {
    como($this->secretaria);

    $this->postJson(SOLICITACOES, pedido())->assertCreated()
        ->assertJsonPath('data.estado', 'pendente')
        ->assertJsonPath('data.duracao_horas', 2)
        ->assertJsonPath('data.permissoes.0', [
            'chave' => 'pessoas.criar', 'categoria_sensivel' => null, 'escopo_tipo' => 'igreja', 'escopo_id' => uuidDe('igrejas', $this->igrejaA),
        ]);

    foreach (['papeis.gerir', 'acessos.gerir', 'pessoas.exportar', 'permissoes.solicitacoes.aprovar'] as $chave) {
        $this->postJson(SOLICITACOES, pedido(['permissoes' => [['chave' => $chave, 'escopo_tipo' => 'organizacao']]]))
            ->assertUnprocessable()->assertJsonPath('codigo', 'permissao_nao_solicitavel');
    }
    foreach ([0, 73] as $horas) {
        $this->postJson(SOLICITACOES, pedido(['duracao_horas' => $horas]))
            ->assertUnprocessable()->assertJsonPath('codigo', 'duracao_concessao_invalida');
    }

    $auditoria = auditoria('authz.permission_requested');
    expect($auditoria)->toHaveCount(1)
        ->and($auditoria[0]->metadados)->toEqual([
            'permissoes' => ['pessoas.criar'], 'escopos' => ['igreja:'.$this->igrejaA], 'categorias_sensiveis' => [], 'duracao_horas' => 2,
        ]);
});

it('exige a categoria explícita para permissão sensível e a mantém na resposta e na auditoria', function (): void {
    como($this->secretaria);
    $saude = ['chave' => 'pessoas.sensiveis.saude.ver', 'escopo_tipo' => 'igreja', 'escopo_id' => uuidDe('igrejas', $this->igrejaA)];

    $this->postJson(SOLICITACOES, pedido(['permissoes' => [$saude]]))
        ->assertUnprocessable()->assertJsonValidationErrors('permissoes.0.categoria_sensivel');
    $this->postJson(SOLICITACOES, pedido(['permissoes' => [[...$saude, 'categoria_sensivel' => 'documentos']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('permissoes.0.categoria_sensivel');

    $this->postJson(SOLICITACOES, pedido(['permissoes' => [[...$saude, 'categoria_sensivel' => 'saude']]]))->assertCreated()
        ->assertJsonPath('data.permissoes.0.categoria_sensivel', 'saude');

    expect(auditoria('authz.permission_requested')[0]->metadados['categorias_sensiveis'])->toBe(['saude']);
});

it('só administrador com teto suficiente aprova, sem autoaprovação nem escalada', function (): void {
    como($this->secretaria);
    $uuid = pedir();

    // Sem `permissoes.solicitacoes.aprovar`.
    $this->postJson(SOLICITACOES."/{$uuid}/aprovar")->assertForbidden()->assertJsonPath('codigo', 'authz.denied');

    // Administrador cujo papel não inclui `pessoas.criar`: escalada.
    $limitado = aprovadorLimitado($this->org, $this->igrejaA);
    $this->postJson(SOLICITACOES."/{$uuid}/aprovar")->assertForbidden()->assertJsonPath('codigo', 'escalada_bloqueada');
    expect(auditoria('authz.escalation_blocked')[0]->metadados['motivo_codigo'])->toBe('teto_do_aprovador');

    // O próprio administrador pedindo para si.
    como($this->admin);
    $proprio = pedir();
    $this->postJson(SOLICITACOES."/{$proprio}/aprovar")->assertForbidden()->assertJsonPath('codigo', 'autoaprovacao_negada');

    $this->postJson(SOLICITACOES."/{$uuid}/aprovar")->assertOk()
        ->assertJsonPath('data.estado', 'aprovada')
        ->assertJsonPath('data.concessao.revogada_em', null);
    expect(auditoria('authz.permission_request_approved'))->toHaveCount(1)
        ->and(auditoria('authz.permission_request_approved')[0]->metadados['solicitante_acesso_id'])->toBe($this->secretaria['acesso'])
        ->and($limitado['acesso'])->not->toBe($this->admin['acesso']);
});

it('com duas decisões sobre o mesmo pedido, a segunda recebe 409 e só existe uma concessão', function (): void {
    como($this->secretaria);
    $uuid = pedir();
    como($this->admin);

    $versao = $this->getJson(SOLICITACOES.'?visao=aprovaveis')->assertOk()->json('data.0.versao');
    $this->postJson(SOLICITACOES."/{$uuid}/aprovar", ['versao' => $versao])->assertOk();
    $this->postJson(SOLICITACOES."/{$uuid}/aprovar", ['versao' => $versao])->assertConflict()->assertJsonPath('codigo', 'solicitacao_permissao_alterada');
    $this->postJson(SOLICITACOES."/{$uuid}/recusar", ['justificativa' => 'tarde demais'])->assertConflict();

    expect(DB::connection('pgsql_dono')->table('concessoes_permissao')->count())->toBe(1);
});

it('recusa com justificativa, cancela só o próprio pedido pendente e revoga concessão vigente', function (): void {
    como($this->secretaria);
    $recusado = pedir();
    $cancelado = pedir();
    $aprovado = pedir();

    como($this->admin);
    $this->postJson(SOLICITACOES."/{$recusado}/recusar")->assertUnprocessable()->assertJsonValidationErrors('justificativa');
    $this->postJson(SOLICITACOES."/{$recusado}/recusar", ['justificativa' => 'Peça só para a igreja A'])->assertOk()
        ->assertJsonPath('data.estado', 'recusada')
        ->assertJsonPath('data.motivo_decisao', 'Peça só para a igreja A');
    $this->postJson(SOLICITACOES."/{$cancelado}/cancelar")->assertForbidden();
    $concessao = $this->postJson(SOLICITACOES."/{$aprovado}/aprovar")->json('data.concessao.uuid');

    como($this->secretaria);
    $this->postJson(SOLICITACOES."/{$cancelado}/cancelar")->assertOk()->assertJsonPath('data.estado', 'cancelada');
    $this->postJson(SOLICITACOES."/{$cancelado}/cancelar")->assertConflict();
    $this->postJson("/api/v1/painel/concessoes-permissao/{$concessao}/revogar")->assertForbidden();
    expect($this->getJson(SOLICITACOES)->assertOk()->json('data'))->toHaveCount(3);

    como($this->admin);
    $this->postJson("/api/v1/painel/concessoes-permissao/{$concessao}/revogar", ['motivo' => 'Mutirão acabou'])->assertNoContent();
    $this->postJson("/api/v1/painel/concessoes-permissao/{$concessao}/revogar")->assertConflict()->assertJsonPath('codigo', 'concessao_encerrada');

    expect(auditoria('authz.permission_request_rejected'))->toHaveCount(1)
        ->and(auditoria('authz.permission_request_cancelled'))->toHaveCount(1)
        ->and(auditoria('authz.permission_grant_revoked'))->toHaveCount(1);
});

it('a concessão vale no alcance só enquanto vigente, sem job nem cache', function (): void {
    $cadastro = fn (): array => [
        'nome' => 'Visitante do mutirão', 'celular' => '(11) 97777-1111', 'vinculo' => 'visitante', 'igreja' => uuidDe('igrejas', $this->igrejaA),
    ];

    como($this->secretaria);
    $this->postJson('/api/v1/painel/pessoas', $cadastro())->assertForbidden();
    $uuid = pedir(['duracao_horas' => 1]);

    como($this->admin);
    $concessao = $this->postJson(SOLICITACOES."/{$uuid}/aprovar")->json('data.concessao.uuid');

    como($this->secretaria);
    $this->postJson('/api/v1/painel/pessoas', $cadastro())->assertCreated();
    expect(collect($this->getJson('/api/v1/me')->json('data.autorizacao.capacidades'))->pluck('permissao'))->toContain('pessoas.criar');

    // Vence na leitura seguinte, sem job: o relógio do banco decide. A concessão de 1h passa a ter
    // começado há 61 min (o `travel` do Laravel não move o `now()` do PostgreSQL).
    DB::connection('pgsql_dono')->table('concessoes_permissao')->where('uuid', $concessao)
        ->update(['inicia_em' => now()->subMinutes(61), 'expira_em' => now()->subMinute()]);
    $this->postJson('/api/v1/painel/pessoas', $cadastro())->assertForbidden();

    // Revogada também deixa de valer na hora.
    como($this->secretaria);
    $outra = pedir();
    como($this->admin);
    $concessao = $this->postJson(SOLICITACOES."/{$outra}/aprovar")->json('data.concessao.uuid');
    $this->postJson("/api/v1/painel/concessoes-permissao/{$concessao}/revogar")->assertNoContent();
    como($this->secretaria);
    $this->postJson('/api/v1/painel/pessoas', $cadastro())->assertForbidden();
});

it('não põe justificativa nem motivo no log técnico', function (): void {
    capturarLog();
    como($this->secretaria);
    $uuid = pedir(['justificativa' => 'Segredo pastoral da família Lima']);
    como($this->admin);
    $this->postJson(SOLICITACOES."/{$uuid}/recusar", ['justificativa' => 'Motivo reservado da recusa'])->assertOk();

    expect(conteudoDoLog())->toContain('authz.permission_requested')
        ->and(conteudoDoLog())->toContain('authz.permission_request_rejected')
        ->and(conteudoDoLog())->not->toContain('Segredo pastoral')
        ->and(conteudoDoLog())->not->toContain('Motivo reservado');
});
