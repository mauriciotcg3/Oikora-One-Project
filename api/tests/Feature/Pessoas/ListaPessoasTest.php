<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Lista de pessoas com filtros, paginação, alcance e projeção (P FR-014; U-17; RN-14; CA-19; T-30). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Lista');
    $this->x = criarIgreja($this->org, 'Igreja X');
    $this->y = criarIgreja($this->org, 'Igreja Y');
    $this->travelTo(Carbon::create(2026, 11, 15, 12, 0, 0, 'America/Sao_Paulo'));
});

function itemDaLista(int $organizacaoId, string $lista, string $nome): int
{
    return inserirComoDono('listas_valores', ['organizacao_id' => $organizacaoId, 'lista' => $lista, 'nome' => $nome, 'ativo' => true, 'ordem' => 1]);
}

/** @return list<string> nomes da resposta, na ordem */
function nomesDaLista(string $consulta = ''): array
{
    return array_column(test()->getJson('/api/v1/painel/pessoas'.$consulta)->assertOk()->json('data'), 'nome');
}

it('aceita só 25, 50 e 100 por página e pagina na ordem pedida', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer]);
    foreach (range(1, 30) as $i) {
        criarPessoa($this->org, $this->x, ['nome' => sprintf('Pessoa %02d', $i)]);
    }

    $this->getJson('/api/v1/painel/pessoas?por_pagina=10')->assertUnprocessable()->assertJsonValidationErrors(['por_pagina']);
    $this->getJson('/api/v1/painel/pessoas?ordem=cpf')->assertUnprocessable();

    $pagina2 = $this->getJson('/api/v1/painel/pessoas?por_pagina=25&pagina=2&ordem=nome')->assertOk();
    // O ator também é uma Pessoa da igreja X.
    expect($pagina2->json('meta.total'))->toBe(31)
        ->and($pagina2->json('meta.por_pagina'))->toBe(25)
        ->and(array_column($pagina2->json('data'), 'nome'))->toBe(['Pessoa 25', 'Pessoa 26', 'Pessoa 27', 'Pessoa 28', 'Pessoa 29', 'Pessoa 30']);
    expect($this->getJson('/api/v1/painel/pessoas?por_pagina=50&ordem=-nome')->json('data.0.nome'))->toBe('Pessoa 30');
});

it('restringe por igreja, vínculo, situação, tipo de membro e função', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer]);
    $efetivo = itemDaLista($this->org, 'tipo_membro', 'Efetivo');
    $musico = itemDaLista($this->org, 'funcao', 'Músico');

    $ana = criarPessoa($this->org, $this->x, ['nome' => 'Ana', 'vinculo' => 'membro', 'situacao' => 'ativa', 'tipo_membro_id' => $efetivo]);
    criarPessoa($this->org, $this->y, ['nome' => 'Bia', 'vinculo' => 'visitante', 'situacao' => 'ativa']);
    criarPessoa($this->org, $this->x, ['nome' => 'Caio', 'vinculo' => 'membro', 'situacao' => 'inativa']);
    $davi = criarPessoa($this->org, $this->y, ['nome' => 'Davi', 'vinculo' => 'frequentador', 'situacao' => 'ativa']);
    inserirComoDono('pessoa_funcoes', ['organizacao_id' => $this->org, 'pessoa_id' => $davi, 'funcao_id' => $musico, 'desde' => '2025-01-01']);
    inserirComoDono('pessoa_funcoes', ['organizacao_id' => $this->org, 'pessoa_id' => $ana, 'funcao_id' => $musico, 'desde' => '2020-01-01', 'ate' => '2021-01-01']);

    expect(nomesDaLista('?igreja='.uuidDe('igrejas', $this->y)))->toBe(['Bia', 'Davi'])
        ->and(nomesDaLista('?vinculo=membro'))->toBe(['Ana', 'Caio'])
        ->and(nomesDaLista('?situacao=inativa'))->toBe(['Caio'])
        ->and(nomesDaLista('?tipo_membro='.uuidDe('listas_valores', $efetivo)))->toBe(['Ana'])
        ->and(nomesDaLista('?funcao='.uuidDe('listas_valores', $musico)))->toBe(['Davi'])
        ->and(nomesDaLista('?igreja=00000000-0000-4000-8000-000000000000'))->toBe([]);
});

it('filtra ficha incompleta pelos obrigatórios do vínculo e aniversariantes do mês', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer]);
    DB::connection('pgsql_dono')->table('campos_obrigatorios')->insert([
        ['organizacao_id' => $this->org, 'vinculo' => 'membro', 'campo' => 'nascimento', 'created_at' => now(), 'updated_at' => now()],
        ['organizacao_id' => $this->org, 'vinculo' => 'membro', 'campo' => 'email', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $completa = criarPessoa($this->org, $this->x, ['nome' => 'Completa', 'vinculo' => 'membro', 'nascimento' => '1990-11-20']);
    criarContato($this->org, $completa, 'email', 'completa@exemplo.test');
    criarPessoa($this->org, $this->x, ['nome' => 'Sem email', 'vinculo' => 'membro', 'nascimento' => '1985-03-02']);
    criarPessoa($this->org, $this->x, ['nome' => 'Visitante', 'vinculo' => 'visitante', 'nascimento' => '2000-11-01']);

    expect(nomesDaLista('?ficha_incompleta=1'))->toBe(['Sem email'])
        ->and(nomesDaLista('?aniversariantes_mes=11'))->toBe(['Completa', 'Visitante']);
});

it('a secretaria lista só o seu alcance, e a lixeira não aparece', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer], 'igreja', $this->x);
    criarPessoa($this->org, $this->x, ['nome' => 'Da X']);
    criarPessoa($this->org, $this->y, ['nome' => 'Da Y']);
    criarPessoa($this->org, $this->x, ['nome' => 'Na lixeira', 'excluida_em' => now()]);

    $nomes = nomesDaLista();
    expect($nomes)->toContain('Da X')
        ->and($nomes)->not->toContain('Da Y')
        ->and($nomes)->not->toContain('Na lixeira');
    expect(nomesDaLista('?igreja='.uuidDe('igrejas', $this->y)))->toBe([]);
});

it('sem permissão de contatos, as colunas sensíveis não vêm nem são selecionadas', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::ContatosVer], 'igreja', $this->x);
    $daX = criarPessoa($this->org, $this->x, ['nome' => 'Com contato']);
    criarContato($this->org, $daX, 'email', 'x@exemplo.test');

    $com = $this->getJson('/api/v1/painel/pessoas?igreja='.uuidDe('igrejas', $this->x))->assertOk()->json('data');
    $linha = collect($com)->firstWhere('nome', 'Com contato');
    expect($linha['contatos']['email'])->toBe('x@exemplo.test');

    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer], 'igreja', $this->x);
    $sql = sqlDe(function () use (&$sem): void {
        $sem = $this->getJson('/api/v1/painel/pessoas')->assertOk()->json('data');
    });
    expect(collect($sem)->firstWhere('nome', 'Com contato'))->not->toHaveKey('contatos')
        ->and(collect($sql)->filter(fn (string $c): bool => str_contains($c, 'pessoa_contatos'))->all())->toBe([]);
});

it('sem pessoas.ver em nenhuma igreja → 403', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::IgrejasGerir]);

    $this->getJson('/api/v1/painel/pessoas')->assertForbidden();
});

it('o total informa o critério, e "ativas por vínculo e igreja" bate com a contagem oficial', function (): void {
    entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer]);
    foreach ([['membro', 'ativa'], ['membro', 'ativa'], ['membro', 'inativa'], ['visitante', 'ativa']] as [$vinculo, $situacao]) {
        criarPessoa($this->org, $this->x, ['vinculo' => $vinculo, 'situacao' => $situacao]);
    }
    criarPessoa($this->org, $this->x, ['vinculo' => 'membro', 'situacao' => 'ativa', 'excluida_em' => now()]);

    $oficial = (int) DB::connection('pgsql_dono')->table('pessoas')
        ->where('organizacao_id', $this->org)->where('igreja_id', $this->x)
        ->where('vinculo', 'membro')->where('situacao', 'ativa')->whereNull('excluida_em')->count();

    $resposta = $this->getJson('/api/v1/painel/pessoas?situacao=ativa&vinculo=membro&igreja='.uuidDe('igrejas', $this->x))->assertOk();
    expect($resposta->json('meta.total'))->toBe($oficial)
        ->and($oficial)->toBe(2)
        ->and($resposta->json('meta.criterio.oficial'))->toBeTrue()
        ->and($resposta->json('meta.criterio.descricao'))->toContain('RN-14');

    $recorte = $this->getJson('/api/v1/painel/pessoas?vinculo=membro')->assertOk();
    expect($recorte->json('meta.criterio.oficial'))->toBeFalse()
        ->and($recorte->json('meta.criterio.filtros'))->toBe(['vinculo' => 'membro']);
});
