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

/** @return object{estado: string, contagem: int, arquivo_path: string} */
function processarExportacao(int $organizacaoId, string $uuid): object
{
    noContexto($organizacaoId, null, fn () => app()->call([new GerarExportacao($organizacaoId, $uuid), 'handle']));

    return DB::connection('pgsql_dono')->table('exportacoes')->where('uuid', $uuid)->first();
}

function itemDaListaParaExportacao(int $organizacaoId, string $lista, string $nome): int
{
    return inserirComoDono('listas_valores', [
        'organizacao_id' => $organizacaoId, 'lista' => $lista, 'nome' => $nome, 'ativo' => true, 'ordem' => 1,
    ]);
}

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

it('usa os mesmos filtros da lista e omite a categoria de fé sem alcance', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasExportar]);
    $tipo = itemDaListaParaExportacao($this->org, 'tipo_membro', 'Efetivo');
    $funcao = itemDaListaParaExportacao($this->org, 'funcao', 'Músico');
    $incluida = criarPessoa($this->org, $this->igreja, [
        'nome' => 'Incluída pelo filtro', 'vinculo' => 'membro', 'situacao' => 'ativa',
        'tipo_membro_id' => $tipo, 'conversao_data' => '2026-11-01',
    ]);
    inserirComoDono('pessoa_funcoes', [
        'organizacao_id' => $this->org, 'pessoa_id' => $incluida, 'funcao_id' => $funcao, 'desde' => '2025-01-01',
    ]);
    criarPessoa($this->org, $this->igreja, [
        'nome' => 'Fora do filtro', 'vinculo' => 'membro', 'situacao' => 'ativa', 'tipo_membro_id' => $tipo,
    ]);

    $uuid = $this->postJson('/api/v1/painel/exportacoes', [
        'tipo' => 'pessoas', 'formato' => 'csv', 'categorias' => ['gerais', 'fe'],
        'filtro' => [
            'igreja' => uuidDe('igrejas', $this->igreja),
            'vinculo' => 'membro', 'situacao' => 'ativa',
            'tipo_membro' => uuidDe('listas_valores', $tipo),
            'funcao' => uuidDe('listas_valores', $funcao),
        ],
    ])->assertAccepted()->json('data.uuid');

    $exportacao = processarExportacao($this->org, $uuid);
    $csv = Storage::disk('arquivos')->get($exportacao->arquivo_path);

    expect($exportacao->contagem)->toBe(1)
        ->and($csv)->toContain('Incluída pelo filtro')
        ->and($csv)->not->toContain('Fora do filtro')
        ->and($csv)->not->toContain('conversao_data');
});

it('reutiliza os filtros de ficha incompleta e aniversariantes do mês', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasExportar]);
    DB::connection('pgsql_dono')->table('campos_obrigatorios')->insert([
        ['organizacao_id' => $this->org, 'vinculo' => 'membro', 'campo' => 'nascimento', 'created_at' => now(), 'updated_at' => now()],
        ['organizacao_id' => $this->org, 'vinculo' => 'membro', 'campo' => 'email', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $completa = criarPessoa($this->org, $this->igreja, ['nome' => 'Completa em novembro', 'vinculo' => 'membro', 'nascimento' => '1990-11-20']);
    criarContato($this->org, $completa, 'email', 'completa@exemplo.test');
    criarPessoa($this->org, $this->igreja, ['nome' => 'Incompleta em março', 'vinculo' => 'membro', 'nascimento' => '1985-03-02']);

    $incompletasUuid = $this->postJson('/api/v1/painel/exportacoes', [
        'tipo' => 'pessoas', 'formato' => 'csv', 'categorias' => ['gerais'], 'filtro' => ['ficha_incompleta' => true],
    ])->assertAccepted()->json('data.uuid');
    $aniversariantesUuid = $this->postJson('/api/v1/painel/exportacoes', [
        'tipo' => 'pessoas', 'formato' => 'csv', 'categorias' => ['gerais'], 'filtro' => ['aniversariantes_mes' => 11],
    ])->assertAccepted()->json('data.uuid');

    $incompletas = processarExportacao($this->org, $incompletasUuid);
    $aniversariantes = processarExportacao($this->org, $aniversariantesUuid);
    $csvIncompletas = Storage::disk('arquivos')->get($incompletas->arquivo_path);
    $csvAniversariantes = Storage::disk('arquivos')->get($aniversariantes->arquivo_path);

    expect($incompletas->contagem)->toBe(1)
        ->and($csvIncompletas)->toContain('Incompleta em março')->not->toContain('Completa em novembro')
        ->and($aniversariantes->contagem)->toBe(1)
        ->and($csvAniversariantes)->toContain('Completa em novembro')->not->toContain('Incompleta em março');
});

it('exporta somente selecionados no alcance em xlsx', function (): void {
    $outraIgreja = criarIgreja($this->org, 'Sem alcance');
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasExportar], 'igreja', $this->igreja);
    $permitida = criarPessoa($this->org, $this->igreja, ['nome' => 'Selecionada permitida']);
    $foraDoAlcance = criarPessoa($this->org, $outraIgreja, ['nome' => 'Selecionada proibida']);

    $uuid = $this->postJson('/api/v1/painel/exportacoes', [
        'tipo' => 'pessoas', 'formato' => 'xlsx', 'categorias' => ['gerais'],
        'selecionados' => [uuidDe('pessoas', $permitida), uuidDe('pessoas', $foraDoAlcance)],
    ])->assertAccepted()->json('data.uuid');

    $exportacao = processarExportacao($this->org, $uuid);
    $arquivo = Storage::disk('arquivos')->get($exportacao->arquivo_path);

    expect($exportacao->contagem)->toBe(1)
        ->and($exportacao->arquivo_path)->toEndWith('.xlsx')
        ->and($arquivo)->toStartWith('PK');
});

it('exporta aniversários de nascimento batismo e casamento sem tratar conversão como aniversário', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasExportar, Permissao::FeVer]);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Casamento', 'data_casamento' => '2020-11-10']);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Batismo', 'batismo_data' => '2020-11-11']);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Nascimento', 'nascimento' => '1990-11-12']);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Só conversão', 'conversao_data' => '2026-11-13']);

    $uuid = $this->postJson('/api/v1/painel/exportacoes', [
        'tipo' => 'aniversarios', 'formato' => 'csv', 'categorias' => ['gerais', 'fe'], 'filtro' => ['mes' => 11],
    ])->assertAccepted()->json('data.uuid');

    $exportacao = processarExportacao($this->org, $uuid);
    $csv = Storage::disk('arquivos')->get($exportacao->arquivo_path);

    expect($exportacao->contagem)->toBe(3)
        ->and($csv)->toContain('Casamento', 'Batismo', 'Nascimento')
        ->and($csv)->toContain('data_casamento', '2020-11-10')
        ->and($csv)->not->toContain('Só conversão');
});
