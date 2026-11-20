<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

/** OIK-SPEC-0001 T-29 — linha do tempo manual com data retroativa e autor (CA-17; P FR-011). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Linha do tempo');
    $this->igreja = criarIgreja($this->org, 'Sede');
    $this->outra = criarIgreja($this->org, 'Bairro');
    $this->ator = entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasEditar], 'igreja', $this->igreja);
    $this->pessoa = uuidDe('pessoas', criarPessoa($this->org, $this->igreja));
});

it('mostra o registro manual com data retroativa na ordem de ocorrido_em, com autor', function (): void {
    $motivo = inserirComoDono('listas_valores', ['organizacao_id' => $this->org, 'lista' => 'motivo_vinculo', 'nome' => 'Decisão pessoal', 'ativo' => true, 'ordem' => 1]);
    $pessoaId = DB::connection('pgsql_dono')->table('pessoas')->where('uuid', $this->pessoa)->value('id');
    inserirComoDono('eventos_linha_do_tempo', [
        'organizacao_id' => $this->org, 'pessoa_id' => $pessoaId, 'tipo' => 'vinculo_alterado', 'ocorrido_em' => '2026-03-01 10:00:00+00',
        'motivo_id' => $motivo, 'origem' => 'painel', 'dados' => json_encode(['de' => 'visitante', 'para' => 'frequentador']),
    ]);

    $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/linha-do-tempo", ['ocorrido_em' => '2020-05-10', 'descricao' => 'Participou do retiro de jovens'])
        ->assertCreated()
        ->assertJsonStructure(['uuid']);
    $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/linha-do-tempo", ['ocorrido_em' => '2024-01-15', 'descricao' => 'Começou no coral'])
        ->assertCreated();

    $resposta = $this->getJson("/api/v1/painel/pessoas/{$this->pessoa}/linha-do-tempo")->assertOk();

    expect(array_column($resposta->json('data'), 'tipo'))->toBe(['vinculo_alterado', 'registro_manual', 'registro_manual'])
        ->and($resposta->json('data.0.motivo'))->toBe('Decisão pessoal')
        ->and($resposta->json('data.1.descricao'))->toBe('Começou no coral')
        ->and($resposta->json('data.2.descricao'))->toBe('Participou do retiro de jovens')
        ->and($resposta->json('data.2.ocorrido_em'))->toStartWith('2020-05-10')
        ->and($resposta->json('data.2.autor.nome'))->toStartWith('Ator ')
        ->and($resposta->json('data.2.autor.acesso'))->toBe(uuidDe('acessos', $this->ator['acesso']))
        ->and($resposta->json('data.2.origem'))->toBe('painel');
});

it('recusa data futura e registro sem descrição', function (): void {
    $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/linha-do-tempo", ['ocorrido_em' => now()->addDays(2)->toDateString(), 'descricao' => 'Futuro'])
        ->assertUnprocessable()->assertJsonValidationErrors(['ocorrido_em']);
    $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/linha-do-tempo", ['ocorrido_em' => '2020-01-01', 'descricao' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors(['descricao']);
});

it('audita o registro só com o nome do campo, sem o texto', function (): void {
    $this->postJson("/api/v1/painel/pessoas/{$this->pessoa}/linha-do-tempo", ['ocorrido_em' => '2021-02-02', 'descricao' => 'Texto que não vai para a auditoria'])
        ->assertCreated();

    $evento = auditoria('pessoa.atualizada')[0];
    expect(json_decode($evento->campos, true))->toBe(['linha_do_tempo'])
        ->and(json_encode($evento))->not->toContain('Texto que não vai');
});

it('nega ler ou registrar na linha do tempo de pessoa de outra igreja', function (): void {
    $fora = uuidDe('pessoas', criarPessoa($this->org, $this->outra));

    $this->getJson("/api/v1/painel/pessoas/{$fora}/linha-do-tempo")->assertForbidden();
    $this->postJson("/api/v1/painel/pessoas/{$fora}/linha-do-tempo", ['ocorrido_em' => '2020-01-01', 'descricao' => 'X'])->assertForbidden();
});
