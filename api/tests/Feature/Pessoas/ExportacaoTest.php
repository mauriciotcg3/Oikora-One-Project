<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Jobs\ExpirarExportacao;
use App\Modules\Pessoas\Application\Jobs\GerarExportacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('arquivos');
    config(['oikora.arquivos.disco' => 'arquivos']);
    Queue::fake();
    $this->org = criarOrganizacao();
    $this->igreja = criarIgreja($this->org);
});

it('gera csv privado com o escopo recalculado e o expira em 24 horas', function (): void {
    $ator = entrarNoPainel($this->org, $this->igreja, [
        Permissao::PessoasVer,
        Permissao::PessoasExportar,
        Permissao::ContatosVer,
        Permissao::FeVer,
    ]);
    $pessoa = criarPessoaComFicha($this->org, $this->igreja, [
        'nome' => 'Ana Exportavel',
        'aceitou_jesus' => true,
        'conversao_data' => '2026-11-01',
        'novo_convertido' => true,
    ]);
    criarContato($this->org, $pessoa, 'email', 'ana@example.test');

    $resposta = $this->postJson('/api/v1/painel/exportacoes', [
        'tipo' => 'novos_convertidos',
        'formato' => 'csv',
        'categorias' => ['gerais', 'contatos', 'fe'],
    ])->assertAccepted()->assertJsonPath('data.estado', 'pendente');
    $uuid = $resposta->json('data.uuid');
    Queue::assertPushed(GerarExportacao::class, fn (GerarExportacao $job): bool => $job->exportacaoUuid === $uuid && $job->queue === 'pesadas');

    noContexto($this->org, null, fn () => app()->call([new GerarExportacao($this->org, $uuid), 'handle']));

    $exportacao = DB::connection('pgsql_dono')->table('exportacoes')->where('uuid', $uuid)->first();
    expect($exportacao->estado)->toBe('concluida')->and($exportacao->contagem)->toBe(1);
    Storage::disk('arquivos')->assertExists($exportacao->arquivo_path);
    expect(Storage::disk('arquivos')->get($exportacao->arquivo_path))
        ->toContain('Ana Exportavel')
        ->toContain('ana@example.test');
    expect(auditoria('exportacao.pedida'))->toHaveCount(1)
        ->and(auditoria('exportacao.concluida'))->toHaveCount(1);

    $this->getJson('/api/v1/painel/exportacoes/'.$uuid)
        ->assertOk()
        ->assertJsonPath('data.estado', 'concluida');

    $this->travelTo(now()->addDay()->addMinute());
    noContexto($this->org, null, fn () => app()->call([new ExpirarExportacao($this->org, $uuid), 'handle']));
    Storage::disk('arquivos')->assertMissing($exportacao->arquivo_path);
    expect(DB::connection('pgsql_dono')->table('exportacoes')->where('uuid', $uuid)->value('estado'))->toBe('expirada');
});

it('nega a geracao se o acesso for revogado depois do pedido', function (): void {
    $ator = entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasExportar]);
    $uuid = $this->postJson('/api/v1/painel/exportacoes', [
        'tipo' => 'pessoas', 'formato' => 'xlsx', 'categorias' => ['gerais'],
    ])->assertAccepted()->json('data.uuid');
    DB::connection('pgsql_dono')->table('acessos')->where('id', $ator['acesso'])->update(['estado' => 'revogado']);

    noContexto($this->org, null, fn () => app()->call([new GerarExportacao($this->org, $uuid), 'handle']));

    $exportacao = DB::connection('pgsql_dono')->table('exportacoes')->where('uuid', $uuid)->first();
    expect($exportacao->estado)->toBe('negada')->and($exportacao->motivo_codigo)->toBe('acesso_revogado');
    expect(auditoria('exportacao.negada'))->toHaveCount(1);
});

it('audita e recusa o pedido sem permissao', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);

    $this->postJson('/api/v1/painel/exportacoes', [
        'tipo' => 'pessoas', 'formato' => 'csv', 'categorias' => ['gerais'],
    ])->assertForbidden()->assertJsonPath('codigo', 'export.denied');

    expect(auditoria('exportacao.negada'))->toHaveCount(1);
});
