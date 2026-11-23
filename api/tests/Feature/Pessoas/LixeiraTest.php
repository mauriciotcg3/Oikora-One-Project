<?php

use App\Modules\Auditoria\Contracts\OperacoesDesfaziveis;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

/** Lixeira, recuperação e purga diária (OIK-ADR-0005 §7; RN-19; U-33; CA-07; T-35). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Lixeira');
    $this->igreja = criarIgreja($this->org, 'Sede');
    config(['lgpd.modo_exclusao' => 'anonimizar']);
});

function naLixeiraHa(int $organizacaoId, int $igrejaId, int $dias, array $dados = []): int
{
    return criarPessoa($organizacaoId, $igrejaId, ['excluida_em' => now()->subDays($dias), 'exclusao_motivo' => 'duplicada', ...$dados]);
}

it('excluir tira a ficha da lista, da busca e dos duplicados, e ela aparece na lixeira', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasExcluir, Permissao::PessoasMesclar]);
    $pessoa = criarPessoa($this->org, $this->igreja, ['nome' => 'Rebeca Lima', 'nascimento' => '1999-09-09']);
    criarPessoa($this->org, $this->igreja, ['nome' => 'Rebeca Lima', 'nascimento' => '1999-09-09']);
    $uuid = uuidDe('pessoas', $pessoa);

    $this->deleteJson("/api/v1/painel/pessoas/{$uuid}", [])->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
    $this->deleteJson("/api/v1/painel/pessoas/{$uuid}", ['motivo' => 'Cadastro repetido'])->assertNoContent();

    expect(array_column($this->getJson('/api/v1/painel/pessoas')->json('data'), 'uuid'))->not->toContain($uuid)
        ->and(array_column($this->getJson('/api/v1/painel/busca?q=Rebeca')->json('data'), 'uuid'))->not->toContain($uuid)
        ->and($this->getJson('/api/v1/painel/duplicados')->json('meta.total'))->toBe(0);

    $lixeira = $this->getJson('/api/v1/painel/lixeira')->assertOk();
    expect($lixeira->json('data.0.uuid'))->toBe($uuid)
        ->and($lixeira->json('data.0.motivo'))->toBe('Cadastro repetido')
        ->and($lixeira->json('data.0.meta.acoes'))->toBe(['recuperar'])
        ->and(auditoria('pessoa.excluida'))->toHaveCount(1)
        ->and(DB::connection('pgsql_dono')->table('eventos_linha_do_tempo')->where('pessoa_id', $pessoa)->value('tipo'))->toBe('excluida');
});

it('recupera em menos de 30 dias com todos os dados; depois disso, não', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasExcluir]);
    $recente = naLixeiraHa($this->org, $this->igreja, 29, ['nome' => 'Recente', 'profissao' => 'Músico']);
    criarContato($this->org, $recente, 'email', 'recente@exemplo.test');
    $antiga = naLixeiraHa($this->org, $this->igreja, 31, ['nome' => 'Antiga']);

    $this->postJson('/api/v1/painel/lixeira/'.uuidDe('pessoas', $recente).'/recuperar')->assertNoContent();
    $this->postJson('/api/v1/painel/lixeira/'.uuidDe('pessoas', $antiga).'/recuperar')
        ->assertUnprocessable()->assertJson(['codigo' => 'prazo_de_recuperacao_vencido']);

    $ficha = DB::connection('pgsql_dono')->table('pessoas')->find($recente);
    expect($ficha->excluida_em)->toBeNull()
        ->and($ficha->profissao)->toBe('Músico')
        ->and(DB::connection('pgsql_dono')->table('pessoa_contatos')->where('pessoa_id', $recente)->count())->toBe(1)
        ->and(auditoria('pessoa.recuperada'))->toHaveCount(1);
});

it('sem pessoas.excluir não exclui nem vê a lixeira', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);
    $pessoa = criarPessoa($this->org, $this->igreja);

    $this->deleteJson('/api/v1/painel/pessoas/'.uuidDe('pessoas', $pessoa), ['motivo' => 'teste'])->assertForbidden();
    $this->getJson('/api/v1/painel/lixeira')->assertForbidden();
});

it('a purga diária roda por organização, com contexto, e remove só as vencidas', function (): void {
    $outraOrg = criarOrganizacao('Outra');
    $outraIgreja = criarIgreja($outraOrg);
    $vencida = naLixeiraHa($this->org, $this->igreja, 31, ['nome' => 'Vencida']);
    $recente = naLixeiraHa($this->org, $this->igreja, 5, ['nome' => 'Recente']);
    $daOutra = naLixeiraHa($outraOrg, $outraIgreja, 40, ['nome' => 'Da outra']);

    $this->artisan('pessoas:purgar-lixeira')->assertSuccessful();

    $exclusoes = DB::connection('pgsql_dono')->table('exclusoes_executadas')->orderBy('pessoa_id')->get();
    expect($exclusoes->pluck('pessoa_id')->all())->toBe([$vencida, $daOutra])
        ->and($exclusoes->firstWhere('pessoa_id', $daOutra)->organizacao_id)->toBe($outraOrg)
        ->and($exclusoes->pluck('modo')->unique()->all())->toBe(['anonimizar'])
        ->and(DB::connection('pgsql_dono')->table('pessoas')->find($vencida)->nome)->toBe('Pessoa removida')
        ->and(DB::connection('pgsql_dono')->table('pessoas')->find($recente)->nome)->toBe('Recente')
        ->and(auditoria('pessoa.purgada'))->toHaveCount(2);

    // Rodar de novo não remove outra vez.
    $this->artisan('pessoas:purgar-lixeira')->assertSuccessful();
    expect(DB::connection('pgsql_dono')->table('exclusoes_executadas')->count())->toBe(2);
});

it('a purga ignora a ficha absorvida enquanto a mesclagem está executada, mesmo depois de 30 dias', function (): void {
    $mantida = criarPessoa($this->org, $this->igreja, ['nome' => 'Mantida']);
    $absorvida = naLixeiraHa($this->org, $this->igreja, 45, ['nome' => 'Absorvida', 'exclusao_motivo' => 'mesclagem']);
    $operacao = noContexto($this->org, null, fn (): array => app(OperacoesDesfaziveis::class)
        ->registrar($this->org, 'mesclagem', null, ['teste' => true], ['mantida_id' => $mantida, 'absorvida_id' => $absorvida]));

    $this->artisan('pessoas:purgar-lixeira')->assertSuccessful();
    expect(DB::connection('pgsql_dono')->table('pessoas')->find($absorvida)->nome)->toBe('Absorvida');

    DB::connection('pgsql_dono')->table('operacoes_desfaziveis')->where('id', $operacao['id'])->update(['estado' => 'expirada']);
    $this->artisan('pessoas:purgar-lixeira')->assertSuccessful();
    expect(DB::connection('pgsql_dono')->table('pessoas')->find($absorvida)->nome)->toBe('Pessoa removida');
});
