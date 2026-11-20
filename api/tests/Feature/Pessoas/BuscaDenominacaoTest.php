<?php

use App\Modules\Autorizacao\Domain\Permissao;

beforeEach(function (): void {
    $this->org = criarOrganizacao('Busca na denominação');
    $this->igrejaX = criarIgreja($this->org, 'Igreja X');
    $this->igrejaY = criarIgreja($this->org, 'Igreja Y');
    $this->deX = criarPessoaComFicha($this->org, $this->igrejaX, ['nome' => 'Joana Lima', 'vinculo' => 'membro']);
    $this->deY = criarPessoaComFicha($this->org, $this->igrejaY, [
        'nome' => 'Joana Souza', 'vinculo' => 'congregado', 'cpf' => '123.456.789-09',
        'tipo_sanguineo' => 'O+', 'aceitou_jesus' => true,
    ]);
    criarContato($this->org, $this->deY, 'email', 'joana.souza@exemplo.test');
    criarContato($this->org, $this->deY, 'celular', '+5511987654321');

    entrarNoPainel($this->org, $this->igrejaX, [Permissao::PessoasVer, Permissao::SaudeVer, Permissao::FeVer], 'igreja', $this->igrejaX);
});

it('devolve de pessoas de outra igreja só uuid, nome, igreja e vínculo, escolhidos no SELECT', function (): void {
    $resposta = null;
    $sql = sqlDe(function () use (&$resposta): void {
        $resposta = $this->getJson('/api/v1/painel/busca?q=joana')->assertOk();
    });

    $deY = collect($resposta->json('data'))->firstWhere('nome', 'Joana Souza');
    expect(array_keys($deY))->toBe(['uuid', 'nome', 'igreja', 'vinculo', 'meta'])
        ->and($deY['igreja'])->toBe(['uuid' => uuidDe('igrejas', $this->igrejaY), 'nome' => 'Igreja Y'])
        ->and($deY['vinculo'])->toBe('congregado')
        ->and($deY['meta']['acoes'])->toBe([]);

    $deX = collect($resposta->json('data'))->firstWhere('nome', 'Joana Lima');
    expect($deX['meta']['acoes'])->toBe(['abrir']);

    $busca = collect($sql)->first(fn (string $consulta): bool => str_contains($consulta, 'from "pessoas"') && str_contains($consulta, 'nome_busca'));
    expect($busca)->toStartWith('select "uuid", "nome", "igreja_id", "vinculo" from "pessoas"')
        ->and($busca)->not->toContain('"igreja_id" in');
});

it('encontra por CPF exato, por e-mail e por celular, com a mesma projeção', function (string $termo): void {
    $this->getJson('/api/v1/painel/busca?q='.urlencode($termo))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nome', 'Joana Souza')
        ->assertJsonMissingPath('data.0.cpf')
        ->assertJsonMissingPath('data.0.contatos');
})->with(['123.456.789-09', '12345678909', 'joana.souza@exemplo.test', '(11) 98765-4321']);

it('não encontra por CPF parcial', function (): void {
    $this->getJson('/api/v1/painel/busca?q=123.456')->assertOk()->assertJsonCount(0, 'data');
});

it('exige pessoas.ver em alguma igreja para buscar', function (): void {
    entrarNoPainel($this->org, $this->igrejaX, [Permissao::PapeisGerir]);

    $this->getJson('/api/v1/painel/busca?q=joana')->assertForbidden();
});
