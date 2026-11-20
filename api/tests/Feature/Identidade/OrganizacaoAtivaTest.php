<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

const ROTA_PAINEL = '/api/v1/painel/permissoes';

/**
 * Liga a conta à organização com um papel que vale na organização inteira e devolve o id do acesso.
 *
 * @param  list<Permissao>  $permissoes
 */
function ligarAOrganizacao(int $contaId, int $organizacaoId, array $permissoes = [Permissao::PapeisGerir]): int
{
    $igreja = criarIgreja($organizacaoId, 'Sede');
    $acesso = criarAcesso($organizacaoId, $contaId, criarPessoa($organizacaoId, $igreja, ['nome' => 'Maria']));
    atribuirPapel($organizacaoId, $acesso, criarPapel($organizacaoId, 'Equipe', $permissoes));

    return $acesso;
}

function entrarPeloPainel(string $email = 'maria@exemplo.test'): string
{
    novoNavegador();
    $cookie = cookieDaSessao(test()->postJson(ROTA_LOGIN, ['identificador' => $email, 'senha' => SENHA, 'entrada' => 'painel'])->assertNoContent());
    comSessao($cookie);

    return $cookie;
}

it('escolhe sozinha a organização de quem tem um único acesso ativo', function (): void {
    $conta = criarContaVerificada();
    $org = criarOrganizacao('Igreja Única');
    ligarAOrganizacao($conta, $org);

    entrarPeloPainel();

    $this->getJson(ROTA_PAINEL)->assertOk();
    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.organizacao_ativa.id', uuidDe('organizacoes', $org))
        ->assertJsonPath('data.organizacao_ativa.nome', 'Igreja Única')
        ->assertJsonCount(1, 'data.organizacoes');
});

it('com dois acessos, espera a escolha e só aceita organização com acesso', function (): void {
    $conta = criarContaVerificada();
    $orgA = criarOrganizacao('A');
    $orgB = criarOrganizacao('B');
    $semAcesso = criarOrganizacao('C');
    ligarAOrganizacao($conta, $orgA);
    ligarAOrganizacao($conta, $orgB);

    entrarPeloPainel();

    $this->getJson(ROTA_PAINEL)->assertForbidden()->assertJsonPath('codigo', 'organizacao_nao_escolhida');
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.organizacao_ativa', null)->assertJsonCount(2, 'data.organizacoes');

    $this->putJson('/api/v1/me/organizacao-ativa', ['organizacao' => uuidDe('organizacoes', $semAcesso)])
        ->assertForbidden()->assertJsonPath('codigo', 'organizacao_sem_acesso');

    $this->putJson('/api/v1/me/organizacao-ativa', ['organizacao' => uuidDe('organizacoes', $orgB)])->assertNoContent();

    $this->getJson(ROTA_PAINEL)->assertOk();
    $this->getJson('/api/v1/me')->assertJsonPath('data.organizacao_ativa.id', uuidDe('organizacoes', $orgB));
});

it('nega a próxima requisição quando o acesso é revogado depois do login', function (): void {
    $conta = criarContaVerificada();
    $org = criarOrganizacao();
    $acesso = ligarAOrganizacao($conta, $org);

    entrarPeloPainel();
    $this->getJson(ROTA_PAINEL)->assertOk();

    DB::connection('pgsql_dono')->table('acessos')->where('id', $acesso)->update(['estado' => 'revogado', 'revogado_em' => now()]);

    $this->getJson(ROTA_PAINEL)->assertForbidden()->assertJsonPath('codigo', 'acesso_revogado');
    $this->getJson(ROTA_PAINEL)->assertForbidden()->assertJsonPath('codigo', 'acesso_inexistente');
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.organizacao_ativa', null)->assertJsonPath('data.autorizacao.capacidades', []);
});

it('confere o X-Organizacao-Esperada contra a organização ativa da sessão', function (): void {
    $conta = criarContaVerificada();
    $org = criarOrganizacao();
    ligarAOrganizacao($conta, $org);
    entrarPeloPainel();

    $this->getJson(ROTA_PAINEL, ['X-Organizacao-Esperada' => (string) $org])
        ->assertStatus(409)->assertJsonPath('codigo', 'organizacao_trocada');
    $this->getJson(ROTA_PAINEL, ['X-Organizacao-Esperada' => uuidDe('organizacoes', $org)])->assertOk();
});

it('devolve a conta, a organização ativa e as capacidades do alcance no /me', function (): void {
    $conta = criarContaVerificada();
    $org = criarOrganizacao();
    $acesso = ligarAOrganizacao($conta, $org, [Permissao::PapeisGerir]);
    $igreja = criarIgreja($org, 'Congregação');
    atribuirPapel($org, $acesso, criarPapel($org, 'Secretaria local', [Permissao::PessoasVer, Permissao::PapeisGerir]), 'igreja', $igreja);

    entrarPeloPainel();

    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.conta.email', 'maria@exemplo.test')
        ->assertJsonPath('data.conta.email_verificado', true)
        ->assertJsonPath('data.autorizacao.capacidades', [
            ['permissao' => Permissao::PapeisGerir->value, 'escopo_tipo' => 'igreja', 'escopo_ids' => [uuidDe('igrejas', $igreja)]],
            ['permissao' => Permissao::PapeisGerir->value, 'escopo_tipo' => 'organizacao', 'escopo_ids' => []],
            ['permissao' => Permissao::PessoasVer->value, 'escopo_tipo' => 'igreja', 'escopo_ids' => [uuidDe('igrejas', $igreja)]],
        ])
        ->assertJsonPath('data.elevacao.ativa', true)
        ->assertJsonPath('data.dois_fatores', ['canal' => null, 'obrigatorio' => false]);
});

it('exige sessão para o /me', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
