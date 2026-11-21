<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

/** OIK-SPEC-0001 T-29 — funções com período e tipo de membro (CA-18, CA-30; P FR-013). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Funções');
    $this->igreja = criarIgreja($this->org, 'Sede');
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasEditar]);
    $this->pessoa = uuidDe('pessoas', criarPessoa($this->org, $this->igreja, ['vinculo' => 'membro', 'matricula' => 'F-1']));
    $item = fn (string $lista, string $nome, bool $ativo = true): string => uuidDe('listas_valores', inserirComoDono('listas_valores', [
        'organizacao_id' => $this->org, 'lista' => $lista, 'nome' => $nome, 'ativo' => $ativo, 'ordem' => 1,
    ]));
    $this->louvor = $item('funcao', 'Louvor');
    $this->inativa = $item('funcao', 'Antiga', false);
    $this->efetivo = $item('tipo_membro', 'Efetivo');
    $this->cooperador = $item('tipo_membro', 'Cooperador');
});

it('atribui função com desde e ate, e recusa ate anterior a desde', function (): void {
    $uuid = $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/funcoes", ['funcao' => $this->louvor, 'desde' => '2024-01-01'])
        ->assertCreated()->json('uuid');
    $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/funcoes", ['funcao' => $this->louvor, 'desde' => '2024-01-01', 'ate' => '2023-12-31'])
        ->assertUnprocessable()->assertJsonValidationErrors(['ate']);

    $this->patchJson("/api/v1/painel/pessoas/{$this->pessoa}/funcoes/{$uuid}", ['ate' => '2023-06-01'])
        ->assertUnprocessable()->assertJsonValidationErrors(['ate']);
    $this->patchJson("/api/v1/painel/pessoas/{$this->pessoa}/funcoes/{$uuid}", ['ate' => '2025-06-30'])->assertOk();

    $this->getJson("/api/v1/painel/pessoas/{$this->pessoa}/funcoes")->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.funcao.nome', 'Louvor')
        ->assertJsonPath('data.0.desde', '2024-01-01')
        ->assertJsonPath('data.0.ate', '2025-06-30');
});

it('aceita só funções ativas da lista', function (): void {
    $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/funcoes", ['funcao' => $this->inativa, 'desde' => '2024-01-01'])
        ->assertUnprocessable()->assertJsonValidationErrors(['funcao']);
    $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/funcoes", ['funcao' => $this->efetivo, 'desde' => '2024-01-01'])
        ->assertUnprocessable()->assertJsonValidationErrors(['funcao']);

    expect(DB::connection('pgsql_dono')->table('pessoa_funcoes')->count())->toBe(0);
});

it('mudar o tipo de membro gera o evento tipo_membro_alterado com de e para', function (): void {
    $this->putJson("/api/v1/painel/pessoas/{$this->pessoa}/tipo-membro", ['tipo_membro' => $this->efetivo])->assertOk();
    $this->putJson("/api/v1/painel/pessoas/{$this->pessoa}/tipo-membro", ['tipo_membro' => $this->efetivo])->assertOk();
    $this->putJson("/api/v1/painel/pessoas/{$this->pessoa}/tipo-membro", ['tipo_membro' => $this->cooperador])->assertOk();

    $eventos = DB::connection('pgsql_dono')->table('eventos_linha_do_tempo')->where('tipo', 'tipo_membro_alterado')->orderBy('id')->pluck('dados')
        ->map(fn (string $dados): array => json_decode($dados, true))->all();
    $tipo = DB::connection('pgsql_dono')->table('pessoas as p')->join('listas_valores as l', 'l.id', '=', 'p.tipo_membro_id')
        ->where('p.uuid', $this->pessoa)->value('l.nome');

    expect($eventos)->toBe([
        ['de' => null, 'para' => $this->efetivo],
        ['de' => $this->efetivo, 'para' => $this->cooperador],
    ])->and($tipo)->toBe('Cooperador')
        ->and(array_map(fn (object $e): array => json_decode($e->campos, true), auditoria('pessoa.atualizada')))
        ->toBe([['tipo_membro'], ['tipo_membro']]);
});

it('recusa tipo de membro de outra lista ou de outra organização', function (): void {
    $outraOrg = criarOrganizacao('Outra');
    $alheio = uuidDe('listas_valores', inserirComoDono('listas_valores', ['organizacao_id' => $outraOrg, 'lista' => 'tipo_membro', 'nome' => 'X', 'ativo' => true, 'ordem' => 1]));

    $this->putJson("/api/v1/painel/pessoas/{$this->pessoa}/tipo-membro", ['tipo_membro' => $this->louvor])
        ->assertUnprocessable()->assertJsonValidationErrors(['tipo_membro']);
    $this->putJson("/api/v1/painel/pessoas/{$this->pessoa}/tipo-membro", ['tipo_membro' => $alheio])
        ->assertUnprocessable()->assertJsonValidationErrors(['tipo_membro']);
});
