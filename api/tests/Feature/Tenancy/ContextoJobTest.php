<?php

use App\Shared\Tenancy\Jobs\ContextoOrganizacaoJob;
use App\Shared\Tenancy\Jobs\JobSemOrganizacao;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function jobComOrganizacaoDeTeste(?int $organizacaoId): object
{
    return new class($organizacaoId)
    {
        public ?Throwable $falha = null;

        public function __construct(public readonly ?int $organizacao_id) {}

        public function fail(Throwable $falha): void
        {
            $this->falha = $falha;
        }
    };
}

beforeEach(function (): void {
    Schema::connection('pgsql_dono')->create('rls_sonda', function (Blueprint $tabela): void {
        $tabela->id()->generatedAs()->always();
        $tabela->organizacao();
        $tabela->text('valor');
    });

    $this->orgA = criarOrganizacao('A');
    $this->middleware = app(ContextoOrganizacaoJob::class);
});

afterEach(function (): void {
    Schema::connection('pgsql_dono')->dropIfExists('rls_sonda');
});

it('falha de forma terminal quando o job não leva organizacao_id', function (): void {
    $executou = false;
    $job = jobComOrganizacaoDeTeste(null);

    $this->middleware->handle($job, function () use (&$executou): void {
        $executou = true;
    });

    expect($executou)->toBeFalse()
        ->and($job->falha)->toBeInstanceOf(RuntimeException::class)
        ->and($job->falha?->getMessage())->toBe('tenant.context.missing');
});

it('grava com a organização válida e limpa o contexto ao terminar', function (): void {
    $job = jobComOrganizacaoDeTeste($this->orgA);

    $this->middleware->handle($job, function () use ($job): void {
        DB::table('rls_sonda')->insert([
            'organizacao_id' => $job->organizacao_id,
            'valor' => 'gravado',
        ]);
    });

    expect($job->falha)->toBeNull()
        ->and(DB::connection('pgsql_dono')->table('rls_sonda')->value('valor'))->toBe('gravado');

    expect(fn () => DB::table('rls_sonda')->count())
        ->toThrow(QueryException::class, 'tenant.context.missing');
});

it('falha de forma terminal quando a organização não existe', function (): void {
    $executou = false;
    $job = jobComOrganizacaoDeTeste(999_999);

    $this->middleware->handle($job, function () use (&$executou): void {
        $executou = true;
    });

    expect($executou)->toBeFalse()
        ->and($job->falha?->getMessage())->toBe('tenant.context.missing');
});

it('executa jobs da lista fechada sem contexto de organização', function (): void {
    $executou = false;

    $this->middleware->handle(new class implements JobSemOrganizacao {}, function () use (&$executou): void {
        $executou = true;
    });

    expect($executou)->toBeTrue();
});
