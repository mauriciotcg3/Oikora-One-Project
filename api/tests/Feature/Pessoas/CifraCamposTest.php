<?php

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use App\Shared\Cifra\CampoSensivelEncrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function chaveDeTeste(string $caractere): string
{
    return 'base64:'.base64_encode(str_repeat($caractere, 32));
}

/** @param list<string> $anteriores */
function configurarCifra(string $atual, array $anteriores = []): void
{
    config([
        'oikora.cifra.campos.chave_atual' => $atual,
        'oikora.cifra.campos.chaves_anteriores' => $anteriores,
        'oikora.cifra.cpf.chave_hmac' => 'hmac-cpf-apenas-para-testes',
    ]);

    app()->forgetInstance(CampoSensivelEncrypter::class);
}

function criarIgrejaParaPessoa(int $organizacaoId): int
{
    return noContexto($organizacaoId, null, fn (): int => (int) DB::table('igrejas')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $organizacaoId,
        'nome' => 'Igreja da pessoa',
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

/** @param array<string, mixed> $dados */
function criarPessoaCifrada(int $organizacaoId, int $igrejaId, array $dados = []): Pessoa
{
    return noContexto($organizacaoId, null, fn (): Pessoa => Pessoa::query()->create([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $organizacaoId,
        'igreja_id' => $igrejaId,
        'nome' => 'João da Silva',
        'cpf' => '123.456.789-09',
        'rg' => '12.345.678-9',
        'rg_pais' => 'BR',
        'necessidades_especiais' => 'Baixa visão',
        'tipo_sanguineo' => 'O+',
        ...$dados,
    ]));
}

beforeEach(function (): void {
    configurarCifra(chaveDeTeste('a'));
});

it('cifra documentos e saúde com chave separada da APP_KEY', function (): void {
    $organizacaoId = criarOrganizacao();
    $pessoa = criarPessoaCifrada($organizacaoId, criarIgrejaParaPessoa($organizacaoId));

    $linha = DB::connection('pgsql_dono')->table('pessoas')->find($pessoa->getKey());

    foreach (['cpf', 'rg', 'rg_pais', 'necessidades_especiais', 'tipo_sanguineo'] as $campo) {
        expect($linha->{$campo})->not->toBe($pessoa->{$campo});
    }

    expect(fn (): string => app('encrypter')->decryptString($linha->cpf))
        ->toThrow(DecryptException::class)
        ->and($linha->cpf_hash)->toBe(hash_hmac('sha256', '12345678909', 'hmac-cpf-apenas-para-testes'))
        ->and($linha->nome_busca)->toBe('joao da silva');

    $recarregada = noContexto($organizacaoId, null, fn (): Pessoa => app(AlcanceAtual::class)->comoSistema($organizacaoId, fn (): Pessoa => Pessoa::query()->findOrFail($pessoa->getKey())));

    expect($recarregada->cpf)->toBe('123.456.789-09')
        ->and($recarregada->rg)->toBe('12.345.678-9')
        ->and($recarregada->rg_pais)->toBe('BR')
        ->and($recarregada->necessidades_especiais)->toBe('Baixa visão')
        ->and($recarregada->tipo_sanguineo)->toBe('O+');
});

it('mantém cpf único na organização mesmo quando a pessoa está na lixeira', function (): void {
    $organizacaoId = criarOrganizacao();
    $igrejaId = criarIgrejaParaPessoa($organizacaoId);

    criarPessoaCifrada($organizacaoId, $igrejaId, ['excluida_em' => now()]);
    criarPessoaCifrada($organizacaoId, $igrejaId, ['cpf' => '12345678909']);
})->throws(QueryException::class, 'unique');

it('lê com a chave anterior e recifra com a chave atual', function (): void {
    $organizacaoId = criarOrganizacao();
    $igrejaId = criarIgrejaParaPessoa($organizacaoId);
    $chaveAnterior = chaveDeTeste('a');

    configurarCifra($chaveAnterior);
    $pessoa = criarPessoaCifrada($organizacaoId, $igrejaId);
    $cpfAnterior = DB::connection('pgsql_dono')->table('pessoas')->where('id', $pessoa->getKey())->value('cpf');

    configurarCifra(chaveDeTeste('b'), [$chaveAnterior]);

    $legivel = noContexto($organizacaoId, null, fn (): Pessoa => app(AlcanceAtual::class)->comoSistema($organizacaoId, fn (): Pessoa => Pessoa::query()->findOrFail($pessoa->getKey())));
    expect($legivel->cpf)->toBe('123.456.789-09');

    expect(Artisan::call('oikora:chaves:recifrar'))->toBe(0);

    $cpfAtual = DB::connection('pgsql_dono')->table('pessoas')->where('id', $pessoa->getKey())->value('cpf');
    $recifrada = noContexto($organizacaoId, null, fn (): Pessoa => app(AlcanceAtual::class)->comoSistema($organizacaoId, fn (): Pessoa => Pessoa::query()->findOrFail($pessoa->getKey())));

    expect($cpfAtual)->not->toBe($cpfAnterior)
        ->and($recifrada->cpf)->toBe('123.456.789-09');
});
