<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Domain\ListaValores;

/** OIK-SPEC-0001 T-25 — obrigatórios por vínculo (RN-13; U-05; U-06; CA-30). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Obrigatórios');
    $this->igreja = criarIgreja($this->org, 'Sede');
});

it('começa com listas e obrigatórios vazios na organização nova', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');

    $this->getJson('/api/v1/painel/configuracao/obrigatorios')->assertOk()
        ->assertExactJson(['data' => ['visitante' => [], 'frequentador' => [], 'membro' => []]]);

    foreach (ListaValores::cases() as $lista) {
        $this->getJson("/api/v1/painel/listas/{$lista->value}")->assertOk()->assertJsonPath('data', []);
    }
});

it('define os obrigatórios de um vínculo sem mexer nos outros', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');

    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'membro', 'campos' => ['nascimento', 'cpf', 'celular']])
        ->assertOk()
        ->assertJsonPath('data.membro', ['celular', 'cpf', 'nascimento'])
        ->assertJsonPath('data.visitante', []);
    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'visitante', 'campos' => ['celular']])->assertOk();
    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'membro', 'campos' => []])->assertOk();

    $this->getJson('/api/v1/painel/configuracao/obrigatorios')->assertOk()
        ->assertExactJson(['data' => ['visitante' => ['celular'], 'frequentador' => [], 'membro' => []]]);
});

it('só aceita chaves do catálogo de campos e vínculos conhecidos', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');

    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'membro', 'campos' => ['cpf', 'signo']])
        ->assertUnprocessable()->assertJsonValidationErrors('campos.1');
    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'membro', 'campos' => ['cpf', 'cpf']])
        ->assertUnprocessable()->assertJsonValidationErrors('campos.0');
    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'lider', 'campos' => ['cpf']])
        ->assertUnprocessable()->assertJsonValidationErrors('vinculo');
    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'membro'])
        ->assertUnprocessable()->assertJsonValidationErrors('campos');

    $this->getJson('/api/v1/painel/configuracao/obrigatorios')->assertJsonPath('data.membro', []);
});

it('nega leitura e mudança a quem não tem pessoas.configurar na organização', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasCriar, Permissao::PessoasEditar], sistema: 'secretaria');

    $this->getJson('/api/v1/painel/configuracao/obrigatorios')->assertForbidden();
    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'membro', 'campos' => ['cpf']])
        ->assertForbidden()->assertJsonPath('codigo', 'authz.denied');
});

it('nega a configuração com pessoas.configurar só numa igreja', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasConfigurar], 'igreja', $this->igreja);

    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'membro', 'campos' => ['cpf']])->assertForbidden();
    $this->postJson('/api/v1/painel/listas/funcao', ['nome' => 'Músico'])->assertForbidden();
});

it('audita a mudança com antes e depois, e não audita o que não mudou', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');

    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'frequentador', 'campos' => ['email', 'celular']])->assertOk();
    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'frequentador', 'campos' => ['celular', 'email']])->assertOk();
    $this->putJson('/api/v1/painel/configuracao/obrigatorios', ['vinculo' => 'frequentador', 'campos' => ['celular']])->assertOk();

    $eventos = auditoria('pessoas.obrigatorios_atualizados');

    expect($eventos)->toHaveCount(2)
        ->and($eventos[0]->alvo_id)->toBe('frequentador')
        ->and($eventos[0]->metadados)->toBe(['vinculo' => 'frequentador', 'campos_antes' => [], 'campos_depois' => ['celular', 'email']])
        ->and($eventos[1]->metadados)->toBe(['vinculo' => 'frequentador', 'campos_antes' => ['celular', 'email'], 'campos_depois' => ['celular']]);
});
