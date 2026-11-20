<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Testing\TestResponse;

/** Secretaria da Igreja A (pode ver, cadastrar e recuperar lá); a Igreja B fica fora do alcance. */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Duplicidade');
    $this->igrejaA = criarIgreja($this->org, 'Igreja A');
    $this->igrejaB = criarIgreja($this->org, 'Igreja B');
    $this->ator = entrarNoPainel($this->org, $this->igrejaA, [Permissao::PessoasVer, Permissao::PessoasCriar, Permissao::PessoasExcluir], 'igreja', $this->igrejaA);
});

function verificar(array $criterios): TestResponse
{
    return test()->getJson('/api/v1/painel/pessoas/duplicidade?'.http_build_query($criterios));
}

it('aponta a pessoa com o mesmo nome e nascimento, ignorando acento e caixa', function (): void {
    $id = criarPessoaComFicha($this->org, $this->igrejaA, ['nome' => 'José da Conceição', 'nascimento' => '1990-05-04', 'vinculo' => 'membro']);

    verificar(['nome' => 'JOSE DA CONCEICAO', 'nascimento' => '1990-05-04'])->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', uuidDe('pessoas', $id))
        ->assertJsonPath('data.0.motivos', ['nome_nascimento'])
        ->assertJsonPath('data.0.meta.acoes', ['abrir']);

    verificar(['nome' => 'José da Conceição', 'nascimento' => '1991-05-04'])->assertOk()->assertJsonCount(0, 'data');
    verificar(['nome' => 'José da Conceição'])->assertOk()->assertJsonCount(0, 'data');
});

it('aponta CPF igual e e-mail igual', function (): void {
    $porCpf = criarPessoaComFicha($this->org, $this->igrejaA, ['nome' => 'Ana', 'cpf' => '529.982.247-25']);
    $porEmail = criarPessoaComFicha($this->org, $this->igrejaA, ['nome' => 'Bia']);
    criarContato($this->org, $porEmail, 'email', 'bia@exemplo.test');

    verificar(['cpf' => '52998224725'])->assertJsonPath('data.0.uuid', uuidDe('pessoas', $porCpf))->assertJsonPath('data.0.motivos', ['cpf']);
    verificar(['email' => 'BIA@exemplo.test'])->assertJsonPath('data.0.uuid', uuidDe('pessoas', $porEmail))->assertJsonPath('data.0.motivos', ['email']);
});

it('mostra só nome, igreja e vínculo do duplicado de outra igreja', function (): void {
    criarPessoaComFicha($this->org, $this->igrejaB, ['nome' => 'Carlos Lima', 'nascimento' => '1980-01-01', 'vinculo' => 'frequentador', 'cpf' => '111.444.777-35']);

    $item = verificar(['nome' => 'Carlos Lima', 'nascimento' => '1980-01-01'])->assertOk()->json('data.0');

    expect(array_keys($item))->toBe(['uuid', 'nome', 'igreja', 'vinculo', 'motivos', 'na_lixeira', 'meta'])
        ->and($item['nome'])->toBe('Carlos Lima')
        ->and($item['igreja']['nome'])->toBe('Igreja B')
        ->and($item['vinculo'])->toBe('frequentador')
        ->and($item['meta']['acoes'])->toBe([]);
});

it('aponta quem está na lixeira com a ação de recuperar', function (): void {
    $id = criarPessoaComFicha($this->org, $this->igrejaA, ['nome' => 'Dora', 'nascimento' => '1970-02-02', 'excluida_em' => now()]);

    verificar(['nome' => 'Dora', 'nascimento' => '1970-02-02'])->assertOk()
        ->assertJsonPath('data.0.uuid', uuidDe('pessoas', $id))
        ->assertJsonPath('data.0.na_lixeira', true)
        ->assertJsonPath('data.0.meta.acoes', ['recuperar']);
});

it('recusa quem não pode cadastrar em nenhuma igreja', function (): void {
    entrarNoPainel($this->org, $this->igrejaA, [Permissao::PessoasVer], 'igreja', $this->igrejaA);

    verificar(['email' => 'x@exemplo.test'])->assertForbidden();
});
