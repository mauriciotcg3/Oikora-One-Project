<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->org = criarOrganizacao('Datas da fé');
    $this->igreja = criarIgreja($this->org, 'Central');
    $this->travelTo(Carbon::create(2026, 11, 25, 12, 0, 0, 'America/Sao_Paulo'));
});

it('lista novos convertidos no alcance sem expor a categoria de fé a quem não pode vê-la', function (): void {
    $ana = criarPessoa($this->org, $this->igreja, ['nome' => 'Ana']);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Bia', 'novo_convertido' => false, 'conversao_data' => '2026-11-20']);

    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);
    $this->getJson('/api/v1/painel/novos-convertidos')->assertForbidden()->assertJsonPath('codigo', 'authz.denied');

    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasEditar, Permissao::FeVer, Permissao::FeEditar]);
    $this->postJson('/api/v1/painel/pessoas/'.uuidDe('pessoas', $ana).'/conversao', [
        'data' => '2026-11-20', 'canal' => 'Culto',
    ])->assertOk()->assertJsonPath('data.alterados', ['aceitou_jesus', 'conversao_data', 'conversao_canal', 'novo_convertido']);

    $resposta = $this->getJson('/api/v1/painel/novos-convertidos')->assertOk();
    expect($resposta->json('data'))->toHaveCount(1)
        ->and($resposta->json('data.0.uuid'))->toBe(uuidDe('pessoas', $ana))
        ->and($resposta->json('meta.criterio.categoria'))->toBe('fe');

    $this->postJson('/api/v1/painel/pessoas/'.uuidDe('pessoas', $ana).'/batismo', [
        'data' => '2026-11-25', 'batizado_por' => 'Pr. João',
    ])->assertOk();
    $this->getJson('/api/v1/painel/novos-convertidos')->assertOk()->assertJsonCount(0, 'data');

    expect((string) DB::connection('pgsql_dono')->table('pessoas')->where('id', $ana)->value('conversao_data'))
        ->toStartWith('2026-11-20');
});

it('lista aniversários de nascimento batismo e casamento por dia semana e mês no fuso da organização', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);
    $this->getJson('/api/v1/painel/aniversarios?periodo=dia')->assertForbidden()->assertJsonPath('codigo', 'authz.denied');

    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::FeVer]);
    criarPessoa($this->org, $this->igreja, [
        'nome' => 'Hoje', 'nascimento' => '1990-11-25', 'batismo_data' => '2020-11-26', 'data_casamento' => '2021-11-30',
    ]);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Lixeira', 'nascimento' => '1990-11-25', 'excluida_em' => now()]);

    $dia = $this->getJson('/api/v1/painel/aniversarios?periodo=dia')->assertOk();
    expect(array_column($dia->json('data'), 'tipo'))->toBe(['nascimento']);

    $semana = $this->getJson('/api/v1/painel/aniversarios?periodo=semana')->assertOk();
    expect(array_column($semana->json('data'), 'tipo'))->toContain('nascimento', 'batismo', 'casamento');

    $mes = $this->getJson('/api/v1/painel/aniversarios?periodo=mes')->assertOk();
    expect(array_column($mes->json('data'), 'tipo'))->toContain('nascimento', 'batismo', 'casamento')
        ->and(array_column($mes->json('data'), 'nome'))->not->toContain('Lixeira')
        ->and($mes->json('meta.criterio.fuso'))->toBe('America/Sao_Paulo');
});

it('inclui os dias do ano seguinte quando a semana cruza a virada do ano', function (): void {
    $this->travelTo(Carbon::create(2026, 12, 29, 12, 0, 0, 'America/Sao_Paulo'));
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::FeVer]);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Dezembro', 'nascimento' => '1990-12-30']);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Janeiro', 'nascimento' => '1990-01-02']);

    $semana = $this->getJson('/api/v1/painel/aniversarios?periodo=semana')->assertOk();
    expect(array_column($semana->json('data'), 'nome'))->toContain('Dezembro', 'Janeiro')
        ->and($semana->json('meta.criterio.fim'))->toBe('2027-01-04');
});
