<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Domain\CampoFicha;

beforeEach(function (): void {
    $this->org = criarOrganizacao('Projeção');
    $this->igreja = criarIgreja($this->org, 'Igreja Central');
    $this->pessoa = criarPessoaComFicha($this->org, $this->igreja, [
        'nome' => 'Carla Mendes',
        'nascimento' => '1990-05-04',
        'cpf' => '987.654.321-00',
        'rg' => '12.345.678-9',
        'necessidades_especiais' => 'Cadeira de rodas',
        'tipo_sanguineo' => 'AB-',
        'aceitou_jesus' => true,
        'batismo_data' => '2010-01-10',
        'profissao' => 'Enfermeira',
    ]);
    criarContato($this->org, $this->pessoa, 'celular', '+5511912345678');
    inserirComoDono('pessoa_enderecos', ['organizacao_id' => $this->org, 'pessoa_id' => $this->pessoa, 'cidade' => 'Santos']);
    $this->rota = '/api/v1/painel/pessoas/'.uuidDe('pessoas', $this->pessoa);
});

/** @return list<string> colunas de `pessoas` da categoria */
function colunasDaCategoria(string $categoria): array
{
    return collect(CampoFicha::cases())
        ->filter(fn (CampoFicha $campo): bool => $campo->categoria() === $categoria && $campo->tabela() === 'pessoas')
        ->map(fn (CampoFicha $campo): string => $campo->value)
        ->values()
        ->all();
}

it('não traz no JSON nem no SELECT os campos da categoria sem permissão de ver', function (string $categoria, Permissao $ver): void {
    $todas = [Permissao::SaudeVer, Permissao::DocumentosVer, Permissao::ContatosVer, Permissao::FeVer];
    $permissoes = [Permissao::PessoasVer, ...array_filter($todas, fn (Permissao $p): bool => $p !== $ver)];
    entrarNoPainel($this->org, $this->igreja, $permissoes, 'igreja', $this->igreja);

    $resposta = null;
    $sql = sqlDe(function () use (&$resposta): void {
        $resposta = $this->getJson($this->rota)->assertOk();
    });

    expect($resposta->json('meta.categorias'))->not->toContain($categoria)
        ->and($resposta->json('data.campos.profissao'))->toBe('Enfermeira');

    $leitura = collect($sql)->last(fn (string $c): bool => str_contains($c, 'from "pessoas"') && str_contains($c, '"nome"'));
    foreach (colunasDaCategoria($categoria) as $coluna) {
        expect($resposta->json('data.campos'))->not->toHaveKey($coluna)
            ->and($leitura)->not->toContain('"'.$coluna.'"');
    }

    if ($categoria === 'contatos') {
        expect($resposta->json('data'))->not->toHaveKeys(['contatos', 'enderecos'])
            ->and(collect($sql)->filter(fn (string $c): bool => str_contains($c, 'pessoa_contatos') || str_contains($c, 'pessoa_enderecos')))->toBeEmpty();
    }
})->with([
    'saúde' => ['saude', Permissao::SaudeVer],
    'documentos' => ['documentos', Permissao::DocumentosVer],
    'contatos' => ['contatos', Permissao::ContatosVer],
    'fé/conversão' => ['fe', Permissao::FeVer],
]);

it('mostra as quatro categorias ao administrador', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');

    $this->getJson($this->rota)
        ->assertOk()
        ->assertJsonPath('meta.categorias', ['saude', 'documentos', 'contatos', 'fe'])
        ->assertJsonPath('data.campos.cpf', '987.654.321-00')
        ->assertJsonPath('data.campos.tipo_sanguineo', 'AB-')
        ->assertJsonPath('data.campos.aceitou_jesus', true)
        ->assertJsonPath('data.campos.nascimento', '1990-05-04')
        ->assertJsonPath('data.contatos.0.valor', '+5511912345678')
        ->assertJsonPath('data.enderecos.0.cidade', 'Santos')
        ->assertJsonPath('meta.acoes', ['editar', 'excluir', 'mesclar', 'transferir']);
});
