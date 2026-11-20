<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

/** OIK-SPEC-0001 T-28 — vínculo, situação e matrícula (CA-06, CA-15, CA-16; RN-03, RN-05, RN-06, RN-07, RN-18). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Vínculos');
    $this->igreja = criarIgreja($this->org, 'Sede');
    $this->motivos = [
        'motivo_vinculo' => uuidDe('listas_valores', criarItemDeLista($this->org, 'motivo_vinculo', 'Decisão pessoal')),
        'motivo_inativacao' => uuidDe('listas_valores', criarItemDeLista($this->org, 'motivo_inativacao', 'Mudou de cidade')),
        'motivo_reativacao' => uuidDe('listas_valores', criarItemDeLista($this->org, 'motivo_reativacao', 'Voltou')),
    ];
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasEditar]);
});

function criarItemDeLista(int $organizacaoId, string $lista, string $nome): int
{
    return inserirComoDono('listas_valores', ['organizacao_id' => $organizacaoId, 'lista' => $lista, 'nome' => $nome, 'ativo' => true, 'ordem' => 1]);
}

function pessoaComVinculo(object $teste, string $vinculo, array $dados = []): string
{
    return uuidDe('pessoas', criarPessoa($teste->org, $teste->igreja, ['vinculo' => $vinculo, ...$dados]));
}

function pessoaComoDono(string $uuid): object
{
    return DB::connection('pgsql_dono')->table('pessoas')->where('uuid', $uuid)->first();
}

/** @return list<object> */
function eventosDaPessoa(string $uuid): array
{
    return DB::connection('pgsql_dono')->table('eventos_linha_do_tempo as e')
        ->join('pessoas as p', 'p.id', '=', 'e.pessoa_id')
        ->where('p.uuid', $uuid)->orderBy('e.id')
        ->get(['e.tipo', 'e.motivo_id', 'e.dados', 'e.origem'])->all();
}

it('recusa Visitante direto para Membro, dizendo que precisa passar por Frequentador, e recusa voltar', function (): void {
    $visitante = pessoaComVinculo($this, 'visitante');
    $membro = pessoaComVinculo($this, 'membro', ['matricula' => 'M-1']);

    $this->postJson("/api/v1/painel/pessoas/{$visitante}/vinculo", ['vinculo' => 'membro', 'motivo' => $this->motivos['motivo_vinculo'], 'matricula' => 'M-2'])
        ->assertUnprocessable()
        ->assertJsonPath('codigo', 'transicao_vinculo_invalida')
        ->assertJsonPath('proximo', 'frequentador')
        ->assertJson(fn ($json) => $json->where('mensagem', fn (string $mensagem): bool => str_contains($mensagem, 'precisa passar por Frequentador'))->etc());

    $this->postJson("/api/v1/painel/pessoas/{$membro}/vinculo", ['vinculo' => 'frequentador', 'motivo' => $this->motivos['motivo_vinculo']])
        ->assertUnprocessable()
        ->assertJsonPath('codigo', 'transicao_vinculo_invalida');

    expect(pessoaComoDono($visitante)->vinculo)->toBe('visitante')
        ->and(eventosDaPessoa($visitante))->toBe([]);
});

it('recusa mudar vínculo, inativar e reativar sem motivo ou com motivo de outra lista', function (): void {
    $pessoa = pessoaComVinculo($this, 'visitante');

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", ['vinculo' => 'frequentador'])
        ->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", ['vinculo' => 'frequentador', 'motivo' => $this->motivos['motivo_inativacao']])
        ->assertUnprocessable()->assertJsonValidationErrors(['motivo' => 'motivo_inexistente_ou_inativo']);
    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/situacao", ['situacao' => 'inativa'])
        ->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/situacao", ['situacao' => 'inativa', 'motivo' => $this->motivos['motivo_reativacao']])
        ->assertUnprocessable()->assertJsonValidationErrors(['motivo' => 'motivo_inexistente_ou_inativo']);

    expect(pessoaComoDono($pessoa)->vinculo)->toBe('visitante')
        ->and(pessoaComoDono($pessoa)->situacao)->toBeNull();
});

it('grava o evento com o motivo e a reativação volta ao mesmo vínculo', function (): void {
    $pessoa = pessoaComVinculo($this, 'visitante');

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", ['vinculo' => 'frequentador', 'motivo' => $this->motivos['motivo_vinculo']])
        ->assertOk()
        ->assertJsonPath('data.vinculo', 'frequentador');
    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/situacao", ['situacao' => 'inativa', 'motivo' => $this->motivos['motivo_inativacao']])
        ->assertOk()
        ->assertJsonPath('data.situacao', 'inativa')
        ->assertJsonPath('data.vinculo', 'frequentador');

    // Inativa não muda de vínculo: reativa primeiro.
    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", ['vinculo' => 'membro', 'motivo' => $this->motivos['motivo_vinculo'], 'matricula' => 'M-9'])
        ->assertUnprocessable()->assertJsonPath('codigo', 'pessoa_inativa');

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/situacao", ['situacao' => 'ativa', 'motivo' => $this->motivos['motivo_reativacao']])
        ->assertOk()
        ->assertJsonPath('data.situacao', 'ativa')
        ->assertJsonPath('data.vinculo', 'frequentador');

    $eventos = eventosDaPessoa($pessoa);
    $motivo = static fn (string $uuid): int => (int) DB::connection('pgsql_dono')->table('listas_valores')->where('uuid', $uuid)->value('id');
    expect(array_column($eventos, 'tipo'))->toBe(['vinculo_alterado', 'situacao_alterada', 'situacao_alterada'])
        ->and(array_map('intval', array_column($eventos, 'motivo_id')))->toBe([
            $motivo($this->motivos['motivo_vinculo']), $motivo($this->motivos['motivo_inativacao']), $motivo($this->motivos['motivo_reativacao']),
        ])
        ->and(json_decode($eventos[0]->dados, true))->toBe(['de' => 'visitante', 'para' => 'frequentador'])
        ->and(json_decode($eventos[2]->dados, true))->toBe(['de' => 'inativa', 'para' => 'ativa'])
        ->and(array_map(static fn (object $linha): array => json_decode($linha->campos, true), auditoria('pessoa.atualizada')))
        ->toBe([['vinculo'], ['situacao'], ['situacao']]);

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/situacao", ['situacao' => 'ativa', 'motivo' => $this->motivos['motivo_reativacao']])
        ->assertUnprocessable()->assertJsonPath('codigo', 'situacao_inalterada');
});

it('bloqueia o estado da pessoa enquanto valida e grava uma transição', function (): void {
    $pessoa = pessoaComVinculo($this, 'visitante');

    $sql = sqlDe(fn () => $this
        ->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", [
            'vinculo' => 'frequentador',
            'motivo' => $this->motivos['motivo_vinculo'],
        ])
        ->assertOk());

    expect(collect($sql)->contains(
        fn (string $consulta): bool => str_contains(strtolower($consulta), 'for update'),
    ))->toBeTrue();
});

it('exige matrícula livre na organização para passar a Membro no modo manual', function (): void {
    $pessoa = pessoaComVinculo($this, 'frequentador');
    $naLixeira = pessoaComVinculo($this, 'membro', ['matricula' => 'M-100', 'excluida_em' => now()]);
    $corpo = ['vinculo' => 'membro', 'motivo' => $this->motivos['motivo_vinculo']];

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", $corpo)
        ->assertUnprocessable()->assertJsonPath('codigo', 'matricula_obrigatoria');
    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", [...$corpo, 'matricula' => 'm-100'])
        ->assertUnprocessable()->assertJsonPath('codigo', 'matricula_em_uso');

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", [...$corpo, 'matricula' => 'M-101'])
        ->assertOk()->assertJsonPath('data.vinculo', 'membro');
    expect(pessoaComoDono($pessoa)->matricula)->toBe('M-101')
        ->and(pessoaComoDono($naLixeira)->matricula)->toBe('M-100');

    $visitante = pessoaComVinculo($this, 'visitante');
    $this->postJson("/api/v1/painel/pessoas/{$visitante}/vinculo", ['vinculo' => 'frequentador', 'motivo' => $this->motivos['motivo_vinculo'], 'matricula' => 'X'])
        ->assertUnprocessable()->assertJsonValidationErrors(['matricula' => 'matricula_so_para_membro']);
});

it('recusa passar a Membro com o modo de matrícula automática (U-07)', function (): void {
    DB::connection('pgsql_dono')->table('organizacoes')->where('id', $this->org)->update(['modo_matricula' => 'automatica']);
    $pessoa = pessoaComVinculo($this, 'frequentador');

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", ['vinculo' => 'membro', 'motivo' => $this->motivos['motivo_vinculo'], 'matricula' => 'M-1'])
        ->assertUnprocessable()->assertJsonPath('codigo', 'matricula_automatica_indisponivel');

    expect(pessoaComoDono($pessoa)->vinculo)->toBe('frequentador');
});

it('desliga novo_convertido ao passar para Membro (RN-18)', function (): void {
    $pessoa = pessoaComVinculo($this, 'frequentador', ['novo_convertido' => true]);

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", ['vinculo' => 'membro', 'motivo' => $this->motivos['motivo_vinculo'], 'matricula' => 'M-7'])
        ->assertOk();

    expect(pessoaComoDono($pessoa)->novo_convertido)->toBeFalse()
        ->and(json_decode(auditoria('pessoa.atualizada')[0]->campos, true))->toBe(['vinculo', 'matricula', 'novo_convertido']);
});

it('recusa sem pessoas.editar na igreja da pessoa', function (): void {
    $pessoa = pessoaComVinculo($this, 'visitante');
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);

    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/vinculo", ['vinculo' => 'frequentador', 'motivo' => $this->motivos['motivo_vinculo']])
        ->assertForbidden();
    $this->postJson("/api/v1/painel/pessoas/{$pessoa}/situacao", ['situacao' => 'inativa', 'motivo' => $this->motivos['motivo_inativacao']])
        ->assertForbidden();
});
