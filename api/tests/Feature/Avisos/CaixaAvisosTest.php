<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Application\TextosDaCaixa;
use App\Modules\Avisos\Domain\TipoAviso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Caixa de avisos e preferências (OIK-ADR-0008 §7, §13; plan.md §7; T-20). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Igreja Central');
    $this->outraOrg = criarOrganizacao('Outra Igreja');
    $igreja = criarIgreja($this->org);

    $outra = criarContaVerificada(['email' => 'outra@exemplo.test', 'celular' => '+5511900001111']);
    criarAcesso($this->org, $outra, criarPessoa($this->org, $igreja));
    $this->outraConta = $outra;

    usarOrganizacao($this->org);
    $this->ator = entrarNoPainel($this->org, $igreja, [Permissao::PessoasVer]);
    $this->conta = $this->ator['conta'];
});

function avisoNaCaixa(int $organizacaoId, int $contaId, TipoAviso $tipo = TipoAviso::TransferenciaSolicitada, ?string $lidoEm = null, string $criadoEm = 'now'): string
{
    $uuid = (string) Str::uuid();
    DB::connection('pgsql_dono')->table('avisos')->insert([
        'uuid' => $uuid,
        'organizacao_id' => $organizacaoId,
        'tipo' => $tipo->value,
        'conta_id' => $contaId,
        'destinatario' => 'conta:'.$contaId,
        'referencia_tipo' => 'teste',
        'referencia_id' => $uuid,
        'destino' => '/painel/transferencias',
        'lido_em' => $lidoEm,
        'created_at' => now()->modify($criadoEm),
        'updated_at' => now(),
    ]);

    return $uuid;
}

it('lista só os avisos da conta na organização ativa, paginados, com o contador de não lidos', function (): void {
    foreach (range(1, 27) as $i) {
        avisoNaCaixa($this->org, $this->conta, criadoEm: "-{$i} minutes");
    }
    avisoNaCaixa($this->org, $this->conta, lidoEm: now()->toDateTimeString(), criadoEm: '-1 hour');
    avisoNaCaixa($this->org, $this->outraConta);
    $outraOrgConta = criarAcesso($this->outraOrg, $this->conta, criarPessoa($this->outraOrg, criarIgreja($this->outraOrg)));
    expect($outraOrgConta)->toBeInt();
    avisoNaCaixa($this->outraOrg, $this->conta);

    $pagina1 = $this->getJson('/api/v1/avisos?por_pagina=25')->assertOk();
    $pagina2 = $this->getJson('/api/v1/avisos?por_pagina=25&pagina=2')->assertOk();

    expect($pagina1->json('data'))->toHaveCount(25)
        ->and($pagina1->json('meta.total'))->toBe(28)
        ->and($pagina1->json('meta.nao_lidos'))->toBe(27)
        ->and($pagina2->json('data'))->toHaveCount(3)
        ->and($pagina2->json('data.2.lido_em'))->not->toBeNull();

    $this->getJson('/api/v1/avisos/nao-lidos')->assertOk()->assertExactJson(['nao_lidos' => 27]);
    $this->getJson('/api/v1/avisos?nao_lidos=1')->assertOk()->assertJsonPath('meta.total', 27);
    $this->getJson('/api/v1/avisos?por_pagina=30')->assertUnprocessable();
});

it('monta o texto na leitura, sem guardar conteúdo no banco', function (): void {
    avisoNaCaixa($this->org, $this->conta, TipoAviso::TransferenciaDecidida);

    $esperado = TextosDaCaixa::do(TipoAviso::TransferenciaDecidida, 'Igreja Central');
    $this->getJson('/api/v1/avisos')->assertOk()
        ->assertJsonPath('data.0.tipo', 'servico.transferencia_decidida')
        ->assertJsonPath('data.0.titulo', $esperado['titulo'])
        ->assertJsonPath('data.0.texto', $esperado['texto'])
        ->assertJsonPath('data.0.destino', '/painel/transferencias');

    $colunas = Schema::connection('pgsql_dono')->getColumnListing('avisos');
    expect($colunas)->not->toContain('texto')
        ->and($colunas)->not->toContain('titulo')
        ->and($colunas)->not->toContain('conteudo');
});

it('marca um aviso e todos como lidos, só os da própria conta', function (): void {
    $primeiro = avisoNaCaixa($this->org, $this->conta);
    avisoNaCaixa($this->org, $this->conta);
    avisoNaCaixa($this->org, $this->conta);
    $deOutro = avisoNaCaixa($this->org, $this->outraConta);

    $this->postJson("/api/v1/avisos/{$primeiro}/lido")->assertNoContent();
    expect(DB::connection('pgsql_dono')->table('avisos')->where('uuid', $primeiro)->value('lido_em'))->not->toBeNull();
    $this->getJson('/api/v1/avisos/nao-lidos')->assertExactJson(['nao_lidos' => 2]);

    $this->postJson("/api/v1/avisos/{$deOutro}/lido")->assertNotFound();
    expect(DB::connection('pgsql_dono')->table('avisos')->where('uuid', $deOutro)->value('lido_em'))->toBeNull();

    $this->postJson('/api/v1/avisos/lidos')->assertOk()->assertJson(['marcados' => 2, 'nao_lidos' => 0]);
    expect(DB::connection('pgsql_dono')->table('avisos')->where('conta_id', $this->conta)->whereNull('lido_em')->count())->toBe(0)
        ->and(DB::connection('pgsql_dono')->table('avisos')->where('uuid', $deOutro)->value('lido_em'))->toBeNull();
});

it('exige sessão para a caixa', function (): void {
    novoNavegador();

    $this->getJson('/api/v1/avisos')->assertUnauthorized();
});

it('mostra e muda as preferências, sem desligar o que é obrigatório', function (): void {
    $lista = collect($this->getJson('/api/v1/conta/preferencias-aviso')->assertOk()->json('data'))->keyBy('tipo');
    expect($lista['convite.painel']['obrigatorio'])->toBeTrue()
        ->and($lista['servico.transferencia_solicitada']['canais'])->toBe([
            ['canal' => 'whatsapp', 'ligado' => true], ['canal' => 'email', 'ligado' => true],
        ]);

    $this->putJson('/api/v1/conta/preferencias-aviso', ['tipo' => 'convite.painel', 'canal' => 'email', 'ligado' => false])
        ->assertUnprocessable()->assertJsonValidationErrors(['canal']);

    $depois = collect($this->putJson('/api/v1/conta/preferencias-aviso', ['tipo' => 'servico.transferencia_solicitada', 'canal' => 'whatsapp', 'ligado' => false])
        ->assertOk()->json('data'))->keyBy('tipo');
    expect($depois['servico.transferencia_solicitada']['canais'][0])->toBe(['canal' => 'whatsapp', 'ligado' => false]);

    $this->putJson('/api/v1/conta/preferencias-aviso', ['tipo' => 'servico.transferencia_solicitada', 'canal' => 'email', 'ligado' => false])
        ->assertUnprocessable();
});
