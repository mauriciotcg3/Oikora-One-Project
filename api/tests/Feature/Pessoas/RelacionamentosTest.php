<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

/** OIK-SPEC-0001 T-29 — relacionamentos com tipos fixos e inversa automática (CA-18; P FR-012; U-24; ASSUMPTION-005). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Relacionamentos');
    $this->igreja = criarIgreja($this->org, 'Sede');
    $this->outra = criarIgreja($this->org, 'Bairro');
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasEditar], 'igreja', $this->igreja);
    $this->a = uuidDe('pessoas', criarPessoa($this->org, $this->igreja, ['nome' => 'Ana Pai']));
    $this->b = uuidDe('pessoas', criarPessoa($this->org, $this->igreja, ['nome' => 'Bruno Filho']));
});

/** @return list<array{de: string, para: string, tipo: string, descricao: string|null}> */
function relacionamentosComoDono(): array
{
    return DB::connection('pgsql_dono')->table('relacionamentos as r')
        ->join('pessoas as de', 'de.id', '=', 'r.pessoa_id')
        ->join('pessoas as para', 'para.id', '=', 'r.relacionada_id')
        ->orderBy('r.id')
        ->get(['de.nome as de', 'para.nome as para', 'r.tipo', 'r.descricao'])
        ->map(fn (object $l): array => (array) $l)->all();
}

it('grava "filho(a)" de B para A quando A é pai/mãe de B, e remover apaga as duas', function (): void {
    $uuid = $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $this->b, 'tipo' => 'pai_mae'])
        ->assertCreated()->json('uuid');

    expect(relacionamentosComoDono())->toBe([
        ['de' => 'Ana Pai', 'para' => 'Bruno Filho', 'tipo' => 'pai_mae', 'descricao' => null],
        ['de' => 'Bruno Filho', 'para' => 'Ana Pai', 'tipo' => 'filho', 'descricao' => null],
    ]);
    $this->getJson("/api/v1/painel/pessoas/{$this->b}/relacionamentos")->assertOk()
        ->assertJsonPath('data.0.tipo', 'filho')
        ->assertJsonPath('data.0.relacionada.uuid', $this->a)
        ->assertJsonPath('data.0.relacionada.nome', 'Ana Pai');

    $this->deleteJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos/{$uuid}")->assertNoContent();

    expect(relacionamentosComoDono())->toBe([]);
});

it('exige descrição em "outro" e não repete o mesmo tipo fixo', function (): void {
    $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $this->b, 'tipo' => 'outro'])
        ->assertUnprocessable()->assertJsonValidationErrors(['descricao']);
    $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $this->b, 'tipo' => 'outro', 'descricao' => 'Padrinho'])
        ->assertCreated();
    $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $this->b, 'tipo' => 'irmao'])->assertCreated();
    $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $this->b, 'tipo' => 'irmao'])
        ->assertUnprocessable()->assertJsonPath('codigo', 'relacionamento_existente');
    $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $this->a, 'tipo' => 'conjuge'])
        ->assertUnprocessable()->assertJsonValidationErrors(['relacionada']);

    expect(array_column(relacionamentosComoDono(), 'descricao'))->toBe(['Padrinho', 'Padrinho', null, null]);
});

it('liga responsável legal e dependente entre um adulto e uma criança', function (): void {
    $adulto = uuidDe('pessoas', criarPessoa($this->org, $this->igreja, ['nome' => 'Carla Adulta', 'nascimento' => '1985-04-02']));
    $crianca = uuidDe('pessoas', criarPessoa($this->org, $this->igreja, ['nome' => 'Davi Criança', 'nascimento' => '2020-09-10']));

    $this->postJson("/api/v1/painel/pessoas/{$adulto}/relacionamentos", ['relacionada' => $crianca, 'tipo' => 'responsavel_legal'])
        ->assertCreated();

    expect(array_column(relacionamentosComoDono(), 'tipo'))->toBe(['responsavel_legal', 'dependente']);
});

it('relaciona com pessoa de outra igreja da organização, mas não edita ficha fora do alcance', function (): void {
    $parente = uuidDe('pessoas', criarPessoa($this->org, $this->outra, ['nome' => 'Elisa Bairro']));
    $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $parente, 'tipo' => 'conjuge'])->assertCreated();
    $this->postJson("/api/v1/painel/pessoas/{$parente}/relacionamentos", ['relacionada' => $this->b, 'tipo' => 'conjuge'])->assertForbidden();

    $outraOrg = criarOrganizacao('Outra');
    $estranha = uuidDe('pessoas', criarPessoa($outraOrg, criarIgreja($outraOrg)));
    $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $estranha, 'tipo' => 'irmao'])
        ->assertUnprocessable()->assertJsonValidationErrors(['relacionada']);
});

it('audita relacionar e remover só com o nome do campo', function (): void {
    $uuid = $this->postJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos", ['relacionada' => $this->b, 'tipo' => 'conjuge'])->json('uuid');
    $this->deleteJson("/api/v1/painel/pessoas/{$this->a}/relacionamentos/{$uuid}")->assertNoContent();

    expect(array_map(fn (object $e): array => json_decode($e->campos, true), auditoria('pessoa.atualizada')))
        ->toBe([['relacionamentos'], ['relacionamentos']]);
});
