<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/** OIK-SPEC-0001 T-22 — convite ao painel e revogação de acesso (CA-02, CA-09; RN-02, RN-12). */
beforeEach(function (): void {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    $this->org = criarOrganizacao('Convites');
    $this->x = criarIgreja($this->org, 'Igreja X');
    $this->y = criarIgreja($this->org, 'Igreja Y');
    $this->secretaria = criarPapel($this->org, 'Secretaria', [Permissao::PessoasVer, Permissao::PessoasEditar], 'secretaria');
    $this->admin = entrarNoPainel($this->org, $this->x, todasAsPermissoes(), sistema: 'administrador');
    $this->convidada = criarPessoa($this->org, $this->x, ['nome' => 'Clara Convidada']);
    criarContato($this->org, $this->convidada, 'email', 'clara@exemplo.test');
    criarContato($this->org, $this->convidada, 'celular', '+5511955554444');
});

function convidarSecretariaEmX(object $teste, ?int $pessoaId = null): string
{
    return $teste->postJson('/api/v1/painel/convites', [
        'pessoa' => uuidDe('pessoas', $pessoaId ?? $teste->convidada),
        'papel' => uuidDe('papeis', $teste->secretaria),
        'escopo_tipo' => 'igreja',
        'escopo' => uuidDe('igrejas', $teste->x),
    ])->assertCreated()->assertJsonPath('estado', 'enviado')->json('uuid');
}

/** O token só existe no link do aviso (no banco fica o hash). */
function tokenDoConvite(string $conviteUuid): string
{
    $destino = (string) DB::connection('pgsql_dono')->table('avisos')
        ->where('referencia_tipo', 'convite')->where('referencia_id', $conviteUuid)->value('destino');

    return substr($destino, strlen('/convites/'));
}

function aceitarComSenha(object $teste, string $token, string $senha = 'senha-da-clara-2026'): TestResponse
{
    novoNavegador();

    return $teste->postJson("/api/v1/convites/{$token}/aceitar", ['senha' => $senha, 'senha_confirmation' => $senha]);
}

it('convida uma Pessoa com papel Secretaria e escopo da igreja X: convite enviado e aviso convite.painel', function (): void {
    $uuid = convidarSecretariaEmX($this);

    $convite = DB::connection('pgsql_dono')->table('convites')->where('uuid', $uuid)->first();
    $aviso = DB::connection('pgsql_dono')->table('avisos')->where('referencia_id', $uuid)->first();
    $token = tokenDoConvite($uuid);

    expect($convite->estado)->toBe('enviado')
        ->and($convite->token_hash)->toBe(hash('sha256', $token))
        ->and($convite->token_hash)->not->toContain($token)
        ->and(strtotime($convite->expira_em) - time())->toBeGreaterThan(71 * 3600)->toBeLessThanOrEqual(72 * 3600)
        ->and($aviso->tipo)->toBe('convite.painel')
        ->and((int) $aviso->pessoa_id)->toBe($this->convidada);

    $this->getJson("/api/v1/convites/{$token}")->assertOk()
        ->assertJsonPath('organizacao.nome', 'Convites')
        ->assertJsonPath('pessoa.nome', 'Clara Convidada')
        ->assertJsonPath('papel.nome', 'Secretaria');
});

it('aceita com senha própria: conta e acesso ligados à Pessoa, atribuição criada, abre ficha de X e não de Y', function (): void {
    $token = tokenDoConvite(convidarSecretariaEmX($this));
    $deX = uuidDe('pessoas', criarPessoa($this->org, $this->x));
    $deY = uuidDe('pessoas', criarPessoa($this->org, $this->y));

    aceitarComSenha($this, $token)->assertCreated()->assertJsonPath('conta_nova', true);

    $conta = DB::connection('pgsql_dono')->table('contas')->where('email', 'clara@exemplo.test')->first();
    $acesso = DB::connection('pgsql_dono')->table('acessos')->where('conta_id', $conta->id)->first();
    $atribuicao = DB::connection('pgsql_dono')->table('atribuicoes_papel')->where('acesso_id', $acesso->id)->first();
    expect($conta->celular)->toBe('+5511955554444')
        ->and((int) $acesso->pessoa_id)->toBe($this->convidada)
        ->and($acesso->estado)->toBe('ativo')
        ->and((int) $atribuicao->papel_id)->toBe($this->secretaria)
        ->and($atribuicao->escopo_tipo)->toBe('igreja')
        ->and((int) $atribuicao->escopo_id)->toBe($this->x)
        ->and(DB::connection('pgsql_dono')->table('convites')->value('estado'))->toBe('aceito');

    dispensarDoisFatores();
    novoNavegador();
    comSessao(cookieDaSessao($this->postJson(ROTA_LOGIN, ['identificador' => 'clara@exemplo.test', 'senha' => 'senha-da-clara-2026', 'entrada' => 'painel'])->assertNoContent()));
    $this->getJson("/api/v1/painel/pessoas/{$deX}")->assertOk();
    $this->getJson("/api/v1/painel/pessoas/{$deY}")->assertForbidden();
});

it('recusa token usado, revogado ou com mais de 72h; o comando diário marca expirado', function (): void {
    $usado = tokenDoConvite(convidarSecretariaEmX($this));
    aceitarComSenha($this, $usado)->assertCreated();
    aceitarComSenha($this, $usado)->assertUnprocessable()->assertJsonPath('codigo', 'convite_invalido');

    comSessao($this->admin['cookie']);
    $outra = criarPessoa($this->org, $this->x);
    criarContato($this->org, $outra, 'email', 'outra@exemplo.test');
    $revogadoUuid = convidarSecretariaEmX($this, $outra);
    $this->deleteJson("/api/v1/painel/convites/{$revogadoUuid}")->assertNoContent();
    aceitarComSenha($this, tokenDoConvite($revogadoUuid))->assertUnprocessable()->assertJsonPath('codigo', 'convite_invalido');

    comSessao($this->admin['cookie']);
    $terceira = criarPessoa($this->org, $this->x);
    criarContato($this->org, $terceira, 'email', 'terceira@exemplo.test');
    $vencidoUuid = convidarSecretariaEmX($this, $terceira);
    DB::connection('pgsql_dono')->table('convites')->where('uuid', $vencidoUuid)->update(['expira_em' => now()->subHours(73)]);
    $this->getJson('/api/v1/convites/'.tokenDoConvite($vencidoUuid))->assertNotFound()->assertJsonPath('codigo', 'convite_invalido');
    aceitarComSenha($this, tokenDoConvite($vencidoUuid))->assertUnprocessable()->assertJsonPath('codigo', 'convite_invalido');

    Artisan::call('convites:expirar');

    expect(DB::connection('pgsql_dono')->table('convites')->orderBy('id')->pluck('estado')->all())->toBe(['aceito', 'revogado', 'expirado']);
});

it('recusa convite para Pessoa sem e-mail e sem celular', function (): void {
    $semContato = criarPessoa($this->org, $this->x);

    $this->postJson('/api/v1/painel/convites', [
        'pessoa' => uuidDe('pessoas', $semContato),
        'papel' => uuidDe('papeis', $this->secretaria),
        'escopo_tipo' => 'igreja',
        'escopo' => uuidDe('igrejas', $this->x),
    ])->assertUnprocessable()->assertJsonValidationErrors(['pessoa']);

    expect(DB::connection('pgsql_dono')->table('convites')->count())->toBe(0);
});

it('quem já tem conta faz login para aceitar; nenhum vínculo é deduzido por e-mail igual', function (): void {
    $existente = criarContaVerificada(['email' => 'clara@exemplo.test', 'celular' => '+5511900001111']);
    $token = tokenDoConvite(convidarSecretariaEmX($this));

    expect(DB::connection('pgsql_dono')->table('acessos')->where('conta_id', $existente)->count())->toBe(0);

    aceitarComSenha($this, $token)->assertStatus(409)->assertJsonPath('codigo', 'entre_para_aceitar');
    expect(DB::connection('pgsql_dono')->table('acessos')->where('conta_id', $existente)->count())->toBe(0)
        ->and(DB::connection('pgsql_dono')->table('contas')->count())->toBe(2);

    // Outra conta, com outro e-mail: o vínculo vem do convite aceito, não do e-mail.
    $outraConta = criarContaVerificada(['email' => 'clara.pessoal@exemplo.test', 'celular' => '+5511900002222']);
    dispensarDoisFatores();
    novoNavegador();
    comSessao(cookieDaSessao(entrarCom('clara.pessoal@exemplo.test')->assertNoContent()));
    $this->postJson("/api/v1/convites/{$token}/aceitar")->assertCreated()->assertJsonPath('conta_nova', false);

    expect((int) DB::connection('pgsql_dono')->table('acessos')->where('pessoa_id', $this->convidada)->value('conta_id'))->toBe($outraConta)
        ->and(DB::connection('pgsql_dono')->table('acessos')->where('conta_id', $existente)->count())->toBe(0);
});

it('revogar o acesso nega a próxima requisição daquela conta ao painel, sem tirar o último Administrador', function (): void {
    $token = tokenDoConvite(convidarSecretariaEmX($this));
    aceitarComSenha($this, $token)->assertCreated();
    dispensarDoisFatores();
    novoNavegador();
    $cookieDaClara = cookieDaSessao($this->postJson(ROTA_LOGIN, ['identificador' => 'clara@exemplo.test', 'senha' => 'senha-da-clara-2026', 'entrada' => 'painel'])->assertNoContent());
    comSessao($cookieDaClara);
    $this->getJson('/api/v1/painel/pessoas/'.uuidDe('pessoas', $this->convidada))->assertOk();

    comSessao($this->admin['cookie']);
    $acesso = DB::connection('pgsql_dono')->table('acessos')->where('pessoa_id', $this->convidada)->value('uuid');
    $this->deleteJson("/api/v1/painel/acessos/{$acesso}")->assertNoContent();
    $this->deleteJson('/api/v1/painel/acessos/'.uuidDe('acessos', $this->admin['acesso']))
        ->assertUnprocessable()->assertJsonPath('codigo', 'ultimo_administrador');

    comSessao($cookieDaClara);
    $this->getJson('/api/v1/painel/pessoas/'.uuidDe('pessoas', $this->convidada))->assertForbidden();
});

it('audita emitir, aceitar e revogar, sem o contato da Pessoa', function (): void {
    $uuid = convidarSecretariaEmX($this);
    aceitarComSenha($this, tokenDoConvite($uuid))->assertCreated();
    comSessao($this->admin['cookie']);
    $acesso = DB::connection('pgsql_dono')->table('acessos')->where('pessoa_id', $this->convidada)->value('uuid');
    $this->deleteJson("/api/v1/painel/acessos/{$acesso}")->assertNoContent();

    $outra = criarPessoa($this->org, $this->x);
    criarContato($this->org, $outra, 'email', 'outra@exemplo.test');
    $revogar = convidarSecretariaEmX($this, $outra);
    $this->deleteJson("/api/v1/painel/convites/{$revogar}")->assertNoContent();

    $emitidos = auditoria('acesso.convite_emitido');
    $concedido = auditoria('acesso.concedido');
    expect($emitidos)->toHaveCount(2)
        ->and($emitidos[0]->metadados)->toMatchArray(['convite_id' => $uuid, 'papel_id' => $this->secretaria, 'escopo_tipo' => 'igreja', 'escopo_id' => $this->x])
        ->and($concedido)->toHaveCount(1)
        ->and($concedido[0]->metadados)->toMatchArray(['convite_id' => $uuid, 'conta_nova' => true])
        ->and($concedido[0]->canal)->toBe('convite')
        ->and(auditoria('acesso.revogado'))->toHaveCount(1)
        ->and(auditoria('acesso.convite_revogado'))->toHaveCount(1)
        ->and(json_encode([$emitidos, $concedido]))->not->toContain('clara@exemplo.test');
});

it('não convida com papel ou escopo que o ator não tem (anti-escalada)', function (): void {
    $limitado = entrarNoPainel($this->org, $this->x, [Permissao::AcessosGerir, Permissao::PessoasVer], 'organizacao');

    $this->postJson('/api/v1/painel/convites', [
        'pessoa' => uuidDe('pessoas', $this->convidada),
        'papel' => uuidDe('papeis', $this->secretaria),
        'escopo_tipo' => 'igreja',
        'escopo' => uuidDe('igrejas', $this->x),
    ])->assertForbidden()->assertJsonPath('codigo', 'escalada_bloqueada');

    expect(auditoria('authz.escalation_blocked'))->toHaveCount(1)
        ->and(DB::connection('pgsql_dono')->table('convites')->count())->toBe(0)
        ->and($limitado['acesso'])->toBeInt();
});
