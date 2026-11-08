<?php

use App\Shared\Tenancy\Contracts\ResolvedorOrganizacaoAtiva;
use App\Shared\Tenancy\Http\ContextoOrganizacao;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::connection('pgsql_dono')->create('rls_sonda', function (Blueprint $tabela): void {
        $tabela->id()->generatedAs()->always();
        $tabela->organizacao();
        $tabela->text('valor');
    });

    $this->orgA = criarOrganizacao('A');
    $this->orgB = criarOrganizacao('B');

    noContexto($this->orgA, null, fn () => DB::table('rls_sonda')->insert([
        'organizacao_id' => $this->orgA,
        'valor' => 'de A',
    ]));
    noContexto($this->orgB, null, fn () => DB::table('rls_sonda')->insert([
        'organizacao_id' => $this->orgB,
        'valor' => 'de B',
    ]));

    app()->instance(ResolvedorOrganizacaoAtiva::class, new class($this->orgA) implements ResolvedorOrganizacaoAtiva
    {
        public function __construct(private readonly int $organizacaoId) {}

        public function resolver(Request $request): int
        {
            return $this->organizacaoId;
        }
    });

    Route::middleware(ContextoOrganizacao::class)->get('/_teste/contexto', fn () => [
        'valores' => DB::table('rls_sonda')->orderBy('id')->pluck('valor')->all(),
    ]);
    Route::middleware(ContextoOrganizacao::class)->post('/_teste/contexto', function (): array {
        DB::table('rls_sonda')->insert([
            'organizacao_id' => $this->orgA,
            'valor' => 'gravado',
        ]);

        return ['gravado' => true];
    });
    Route::get('/_teste/sem-contexto', fn () => [
        'total' => DB::table('rls_sonda')->count(),
    ]);
});

afterEach(function (): void {
    Schema::connection('pgsql_dono')->dropIfExists('rls_sonda');
});

it('limita a requisição às linhas da organização resolvida', function (): void {
    $this->getJson('/_teste/contexto')
        ->assertOk()
        ->assertExactJson(['valores' => ['de A']]);
});

it('não vaza o contexto para a requisição seguinte na mesma conexão', function (): void {
    $this->getJson('/_teste/contexto')->assertOk();

    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/_teste/sem-contexto'))
        ->toThrow(QueryException::class, 'tenant.context.missing');
});

it('recusa organização esperada divergente antes de executar a rota', function (): void {
    $this->withHeader('X-Organizacao-Esperada', (string) $this->orgB)
        ->postJson('/_teste/contexto')
        ->assertStatus(409)
        ->assertJson(['codigo' => 'organizacao_trocada']);

    expect(DB::connection('pgsql_dono')->table('rls_sonda')->where('valor', 'gravado')->exists())->toBeFalse();
});
