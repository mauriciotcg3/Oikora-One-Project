<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use Illuminate\Support\Facades\DB;

const PAINEL = '/api/v1/painel/permissoes';
const ELEVACAO = '/api/v1/painel/elevacao';

/** Conta com acesso e papel na organização; `administrador` usa o papel de sistema (RN-16). */
function contaNoPainel(?string $sistema = null): array
{
    $org = criarOrganizacao();
    $conta = criarContaVerificada();
    $igreja = criarIgreja($org, 'Sede');
    $acesso = criarAcesso($org, $conta, criarPessoa($org, $igreja, ['nome' => 'Maria']));
    atribuirPapel($org, $acesso, criarPapel($org, $sistema === null ? 'Equipe' : ucfirst($sistema), [Permissao::PapeisGerir], $sistema));

    return ['org' => $org, 'conta' => $conta, 'acesso' => $acesso];
}

function ultimoCodigo(): string
{
    return collect(app(PostmarkFalso::class)->enviados)->last()->codigo;
}

/** Eleva pelo diálogo e devolve o cookie da sessão regenerada. */
function elevar(array $dados): string
{
    $cookie = cookieDaSessao(test()->postJson(ELEVACAO, $dados)->assertNoContent());
    comSessao($cookie);

    return $cookie;
}

it('recusa rota do painel sem elevação e eleva com a senha', function (): void {
    contaNoPainel();
    logarNaConta();

    $this->getJson(PAINEL)->assertForbidden()->assertJsonPath('codigo', 'elevacao_necessaria');
    $this->postJson(ELEVACAO, ['senha' => 'errada-123'])->assertUnprocessable()->assertJsonValidationErrors('senha');

    elevar(['senha' => SENHA]);

    $this->getJson(PAINEL)->assertOk();
});

it('expira a elevação com 2h sem uso', function (): void {
    contaNoPainel();
    logarNaConta();
    elevar(['senha' => SENHA]);

    $this->travel(119)->minutes();
    $this->getJson(PAINEL)->assertOk();

    $this->travel(119)->minutes();
    $this->getJson(PAINEL)->assertOk();

    $this->travel(121)->minutes();
    $this->getJson(PAINEL)->assertForbidden()->assertJsonPath('codigo', 'elevacao_necessaria');
});

it('expira a elevação com 12h no total, mesmo em uso', function (): void {
    contaNoPainel();
    logarNaConta();
    elevar(['senha' => SENHA]);

    foreach (range(1, 6) as $_) {
        $this->travel(110)->minutes();
        $this->getJson(PAINEL)->assertOk();
    }

    $this->travel(70)->minutes();
    $this->getJson(PAINEL)->assertForbidden()->assertJsonPath('codigo', 'elevacao_necessaria');
});

it('não deixa o Administrador sem 2FA cadastrado entrar no painel', function (): void {
    contaNoPainel('administrador');

    // O login pela entrada `painel` funciona, mas não abre a elevação.
    $cookie = cookieDaSessao($this->postJson(ROTA_LOGIN, ['identificador' => 'maria@exemplo.test', 'senha' => SENHA, 'entrada' => 'painel'])->assertNoContent());
    comSessao($cookie);

    $this->getJson(PAINEL)->assertForbidden()->assertJsonPath('codigo', 'elevacao_necessaria');
    $this->postJson(ELEVACAO, ['senha' => SENHA])->assertForbidden()->assertJsonPath('codigo', 'dois_fatores_cadastro_necessario');
    $this->getJson('/api/v1/me')->assertJsonPath('data.dois_fatores.obrigatorio', true);
});

it('não confunde papel customizado com acesso total com o Administrador de sistema', function (): void {
    $org = criarOrganizacao();
    $conta = criarContaVerificada();
    $igreja = criarIgreja($org, 'Sede');
    $acesso = criarAcesso($org, $conta, criarPessoa($org, $igreja, ['nome' => 'Prévia']));
    atribuirPapel($org, $acesso, criarPapel($org, 'Acesso total de prévia', Permissao::cases()));

    $cookie = cookieDaSessao($this->postJson(ROTA_LOGIN, [
        'identificador' => 'maria@exemplo.test',
        'senha' => SENHA,
        'entrada' => 'painel',
    ])->assertNoContent());
    comSessao($cookie);

    $this->getJson(PAINEL)->assertOk();
    $this->getJson('/api/v1/me')
        ->assertJsonPath('data.dois_fatores.obrigatorio', false)
        ->assertJsonCount(count(Permissao::cases()), 'data.autorizacao.capacidades');
});

it('exige o código do 2FA na elevação do Administrador que o cadastrou', function (): void {
    contaNoPainel('administrador');
    $cookie = logarNaConta();
    ativarDoisFatores($cookie);
    comSessao($cookie);

    $this->postJson(ELEVACAO, ['senha' => SENHA])->assertForbidden()
        ->assertJsonPath('codigo', 'dois_fatores_necessario')
        ->assertJsonPath('canal', 'email');
    $this->postJson(ELEVACAO, ['senha' => SENHA, 'codigo' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('codigo');

    elevar(['senha' => SENHA, 'codigo' => ultimoCodigo()]);

    $this->getJson(PAINEL)->assertOk();
});

it('deixa a Secretaria elevar só com a senha, até ela ativar o 2FA', function (): void {
    contaNoPainel('secretaria');
    $cookie = logarNaConta();
    elevar(['senha' => SENHA]);
    $this->getJson(PAINEL)->assertOk();

    $cookie = logarNaConta();
    ativarDoisFatores($cookie);
    comSessao($cookie);

    $this->postJson(ELEVACAO, ['senha' => SENHA])->assertForbidden()->assertJsonPath('codigo', 'dois_fatores_necessario');
    elevar(['senha' => SENHA, 'codigo' => ultimoCodigo()]);
    $this->getJson(PAINEL)->assertOk();
});

it('exige ao menos uma atribuição de papel nas rotas do painel', function (): void {
    $org = criarOrganizacao();
    $conta = criarContaVerificada();
    criarAcesso($org, $conta, criarPessoa($org, criarIgreja($org, 'Sede'), ['nome' => 'Maria']));
    logarNaConta();
    elevar(['senha' => SENHA]);

    $this->getJson(PAINEL)->assertForbidden()->assertJsonPath('codigo', 'sem_atribuicao');
});

it('abre a elevação no login pela entrada painel, também depois do 2FA', function (): void {
    contaNoPainel('administrador');
    $cookie = logarNaConta();
    ativarDoisFatores($cookie);

    novoNavegador();
    $resposta = $this->postJson(ROTA_LOGIN, ['identificador' => 'maria@exemplo.test', 'senha' => SENHA, 'entrada' => 'painel'])
        ->assertForbidden()->assertJsonPath('codigo', 'dois_fatores_necessario');
    comSessao(cookieDaSessao($resposta));
    $cookie = cookieDaSessao($this->postJson('/api/v1/auth/2fa/confirmar', ['codigo' => ultimoCodigo()])->assertNoContent());
    comSessao($cookie);

    $this->getJson(PAINEL)->assertOk();
});

it('registra a elevação na auditoria da organização e nos eventos de autenticação', function (): void {
    $dados = contaNoPainel();
    logarNaConta();
    elevar(['senha' => SENHA]);

    $auditoria = auditoria('auth.elevacao');
    expect($auditoria)->toHaveCount(1)
        ->and((int) $auditoria[0]->organizacao_id)->toBe($dados['org'])
        ->and((int) $auditoria[0]->acesso_id)->toBe($dados['acesso'])
        ->and($auditoria[0]->metadados)->toBe(['via' => 'dialogo', 'segundo_fator' => null])
        ->and(collect(eventos())->where('evento', 'auth.elevacao')->count())->toBe(1);

    novoNavegador();
    $this->postJson(ROTA_LOGIN, ['identificador' => 'maria@exemplo.test', 'senha' => SENHA, 'entrada' => 'painel'])->assertNoContent();

    expect(auditoria('auth.elevacao'))->toHaveCount(2)
        ->and(auditoria('auth.elevacao')[1]->metadados['via'])->toBe('login')
        ->and(DB::connection('pgsql_dono')->table('eventos_autenticacao')->where('evento', 'auth.elevacao')->count())->toBe(2);
});
