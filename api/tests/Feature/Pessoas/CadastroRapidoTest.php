<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->org = criarOrganizacao('Cadastro rápido');
    $this->igrejaA = criarIgreja($this->org, 'Igreja A');
    $this->igrejaB = criarIgreja($this->org, 'Igreja B');
    $this->comoChegou = inserirComoDono('listas_valores', ['organizacao_id' => $this->org, 'lista' => 'como_chegou', 'nome' => 'Convite de amigo', 'ordem' => 1]);
    $this->ator = entrarNoPainel($this->org, $this->igrejaA, [Permissao::PessoasVer, Permissao::PessoasCriar], 'igreja', $this->igrejaA);
});

function cadastro(array $mudancas = []): array
{
    return [
        'nome' => '  Maria   Souza ',
        'celular' => '(11) 98765-4321',
        'vinculo' => 'visitante',
        'igreja' => uuidDe('igrejas', test()->igrejaA),
        'como_chegou' => uuidDe('listas_valores', test()->comoChegou),
        ...$mudancas,
    ];
}

function pessoaPorUuid(string $uuid): object
{
    return DB::connection('pgsql_dono')->table('pessoas')->where('uuid', $uuid)->first();
}

it('salva com nome, celular, vínculo, igreja e como chegou, e gera o evento de entrada', function (): void {
    $uuid = $this->postJson('/api/v1/painel/pessoas', cadastro())->assertCreated()->json('data.uuid');

    $pessoa = pessoaPorUuid($uuid);
    $evento = DB::connection('pgsql_dono')->table('eventos_linha_do_tempo')->where('pessoa_id', $pessoa->id)->first();
    $contato = DB::connection('pgsql_dono')->table('pessoa_contatos')->where('pessoa_id', $pessoa->id)->first();

    expect($pessoa->nome)->toBe('Maria Souza')
        ->and((int) $pessoa->igreja_id)->toBe($this->igrejaA)
        ->and($pessoa->vinculo)->toBe('visitante')
        ->and((int) $pessoa->como_chegou_id)->toBe($this->comoChegou)
        ->and($contato->tipo)->toBe('celular')
        ->and($contato->valor)->toBe('+5511987654321')
        ->and($evento->tipo)->toBe('entrada')
        ->and((int) $evento->motivo_id)->toBe($this->comoChegou)
        ->and((int) $evento->autor_acesso_id)->toBe($this->ator['acesso'])
        ->and(auditoria('pessoa.criada'))->toHaveCount(1)
        ->and(json_decode(auditoria('pessoa.criada')[0]->campos, true))->toContain('celular', 'como_chegou_id');
});

it('aceita e-mail no lugar do celular e recusa sem nenhum dos dois', function (): void {
    $this->postJson('/api/v1/painel/pessoas', cadastro(['celular' => null, 'email' => 'Maria@Exemplo.test']))->assertCreated();
    expect(DB::connection('pgsql_dono')->table('pessoa_contatos')->where('tipo', 'email')->value('valor'))->toBe('maria@exemplo.test');

    $this->postJson('/api/v1/painel/pessoas', cadastro(['celular' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['celular', 'email']);
});

it('não exige os obrigatórios do vínculo no cadastro rápido', function (): void {
    DB::connection('pgsql_dono')->table('campos_obrigatorios')->insert([
        'organizacao_id' => $this->org, 'vinculo' => 'visitante', 'campo' => 'nascimento', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson('/api/v1/painel/pessoas', cadastro())->assertCreated();
});

it('grava o par "é outra pessoa" e segue com o cadastro', function (): void {
    $parecida = criarPessoaComFicha($this->org, $this->igrejaB, ['nome' => 'Maria Souza', 'nascimento' => '1990-01-01']);

    $uuid = $this->postJson('/api/v1/painel/pessoas', cadastro([
        'nascimento' => '1990-01-01',
        'nao_duplicadas' => [uuidDe('pessoas', $parecida)],
    ]))->assertCreated()->json('data.uuid');

    $nova = (int) pessoaPorUuid($uuid)->id;
    $par = DB::connection('pgsql_dono')->table('pares_nao_duplicados')->first();

    expect((int) $par->pessoa_a_id)->toBe(min($nova, $parecida))
        ->and((int) $par->pessoa_b_id)->toBe(max($nova, $parecida))
        ->and((int) $par->confirmado_por_acesso_id)->toBe($this->ator['acesso']);
});

it('recusa CPF igual ao de outra pessoa da organização, mesmo na lixeira', function (): void {
    criarPessoaComFicha($this->org, $this->igrejaB, ['nome' => 'Outra', 'cpf' => '529.982.247-25', 'excluida_em' => now()]);

    $this->postJson('/api/v1/painel/pessoas', cadastro(['cpf' => '529.982.247-25']))
        ->assertUnprocessable()->assertJsonPath('codigo', 'cpf_em_uso');
    expect(auditoria('pessoa.criada'))->toBe([]);
});

it('recusa cadastrar em igreja fora do alcance', function (): void {
    $this->postJson('/api/v1/painel/pessoas', cadastro(['igreja' => uuidDe('igrejas', $this->igrejaB)]))
        ->assertForbidden()->assertJsonPath('codigo', 'authz.denied');
});

it('recusa celular inválido e "como chegou" inexistente', function (): void {
    $this->postJson('/api/v1/painel/pessoas', cadastro(['celular' => '123', 'como_chegou' => '00000000-0000-4000-8000-000000000000']))
        ->assertUnprocessable()->assertJsonValidationErrors(['celular', 'como_chegou']);
});
