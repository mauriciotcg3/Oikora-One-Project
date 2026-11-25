<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->org = criarOrganizacao('Datas da fé');
    $this->igreja = criarIgreja($this->org, 'Central');
    $this->travelTo(Carbon::create(2026, 11, 25, 12, 0, 0, 'America/Sao_Paulo'));
});

it('lista novos convertidos no alcance sem expor a categoria de fé a quem não pode vê-la', function (): void {
    $ana = criarPessoa($this->org, $this->igreja, ['nome' => 'Ana', 'novo_convertido' => true, 'conversao_data' => '2026-11-20']);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Bia', 'novo_convertido' => false, 'conversao_data' => '2026-11-20']);

    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);
    $this->getJson('/api/v1/painel/novos-convertidos')->assertForbidden()->assertJsonPath('codigo', 'authz.denied');

    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::FeVer]);
    $resposta = $this->getJson('/api/v1/painel/novos-convertidos')->assertOk();
    expect($resposta->json('data'))->toHaveCount(1)
        ->and($resposta->json('data.0.uuid'))->toBe(uuidDe('pessoas', $ana))
        ->and($resposta->json('meta.criterio.categoria'))->toBe('fe');
});

it('lista aniversários de nascimento conversão e batismo por dia semana e mês no fuso da organização', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::FeVer]);
    criarPessoa($this->org, $this->igreja, [
        'nome' => 'Hoje', 'nascimento' => '1990-11-25', 'conversao_data' => '2020-11-26', 'batismo_data' => '2021-12-10',
    ]);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Lixeira', 'nascimento' => '1990-11-25', 'excluida_em' => now()]);

    $dia = $this->getJson('/api/v1/painel/aniversarios?periodo=dia')->assertOk();
    expect(array_column($dia->json('data'), 'tipo'))->toBe(['nascimento']);

    $semana = $this->getJson('/api/v1/painel/aniversarios?periodo=semana')->assertOk();
    expect(array_column($semana->json('data'), 'tipo'))->toContain('nascimento', 'conversao');

    $mes = $this->getJson('/api/v1/painel/aniversarios?periodo=mes')->assertOk();
    expect(array_column($mes->json('data'), 'tipo'))->toContain('nascimento', 'conversao')
        ->and(array_column($mes->json('data'), 'nome'))->not->toContain('Lixeira')
        ->and($mes->json('meta.criterio.fuso'))->toBe('America/Sao_Paulo');
});
