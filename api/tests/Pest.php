<?php

use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use App\Modules\Identidade\Application\ExigenciaDeDoisFatores;
use App\Modules\Identidade\Contracts\CodigosDeIdentidade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Rls');

const ROTA_LOGIN = '/api/v1/auth/login';
const SENHA = 'senha-correta-123';

/** @return list<object> */
function eventos(): array
{
    return DB::connection('pgsql_dono')->table('eventos_autenticacao')->orderBy('id')->get()->all();
}

function entrarCom(string $identificador, string $senha = SENHA): TestResponse
{
    return test()->postJson(ROTA_LOGIN, ['identificador' => $identificador, 'senha' => $senha]);
}

function cookieDaSessao(TestResponse $resposta): string
{
    return $resposta->getCookie(config('session.cookie'), decrypt: false)->getValue();
}

/** Simula uma nova requisição do navegador: só o cookie identifica a pessoa. */
function comSessao(string $cookie): void
{
    Auth::forgetGuards();
    // `auth.driver` guarda a instância antiga do guard, e é por ela que a sessão grava o `user_id`.
    app()->forgetInstance('auth.driver');
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    test()->flushHeaders();
    test()->withCredentials();
    test()->withUnencryptedCookie(config('session.cookie'), $cookie);
}

/** Cria a organização pelo `oikora_dono`, fora da RLS, e devolve o id. */
function criarOrganizacao(string $nome = 'Igreja Teste'): int
{
    return (int) DB::connection('pgsql_dono')->table('organizacoes')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'nome' => $nome,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Cria a conta pelo `oikora_dono`, como faria o aceite de convite (T-22), e devolve o id. */
function criarConta(array $dados = []): int
{
    return (int) DB::connection('pgsql_dono')->table('contas')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'email' => 'maria@exemplo.test',
        'celular' => '+5511987654321',
        'password' => Hash::make('senha-correta-123'),
        'estado' => 'ativa',
        'created_at' => now(),
        'updated_at' => now(),
        ...$dados,
    ]);
}

/** Liga uma conta a uma organização e devolve o id do acesso. */
function criarAcesso(int $organizacaoId, int $contaId, int $pessoaId, string $estado = 'ativo'): int
{
    return (int) DB::connection('pgsql_dono')->table('acessos')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $organizacaoId,
        'conta_id' => $contaId,
        'pessoa_id' => $pessoaId,
        'estado' => $estado,
        'origem' => 'convite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Roda `$acao` numa transação do `oikora_app` com o contexto gravado (OIK-ADR-0011 §3). */
function noContexto(?int $organizacaoId, ?int $contaId, Closure $acao): mixed
{
    return DB::transaction(function () use ($organizacaoId, $contaId, $acao): mixed {
        if ($organizacaoId !== null) {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
        }
        if ($contaId !== null) {
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);
        }

        return $acao();
    });
}

/** Simula outro navegador: sem cookie, nada da sessão anterior é herdado. */
function novoNavegador(): void
{
    comSessao('');
}

/** Emite um código pelo Contract público de Identidade e devolve o id em `codigos_verificacao`. */
function emitirCodigo(int $contaId, string $finalidade, string $canal): int
{
    return app(CodigosDeIdentidade::class)->emitir($contaId, $finalidade, $canal);
}

function criarContaVerificada(array $dados = []): int
{
    return criarConta(['email_verificado_em' => now(), 'celular_verificado_em' => now(), ...$dados]);
}

/** Entra na conta já criada, sem 2FA, e deixa o navegador com a sessão dela. */
function logarNaConta(): string
{
    $cookie = cookieDaSessao(entrarCom('maria@exemplo.test')->assertNoContent());
    comSessao($cookie);

    return $cookie;
}

function logado(array $dados = []): string
{
    criarContaVerificada($dados);

    return logarNaConta();
}

function confirmarSenhaAtual(string $cookie): void
{
    comSessao($cookie);
    test()->postJson('/api/v1/conta/confirmar-senha', ['senha' => SENHA])->assertNoContent();
    comSessao($cookie);
}

/** Ativa o 2FA por e-mail e devolve os códigos de recuperação. */
function ativarDoisFatores(string $cookie): array
{
    confirmarSenhaAtual($cookie);

    return test()->postJson('/api/v1/conta/2fa', ['canal' => 'email'])->assertOk()->json('codigos_recuperacao');
}

/** Até a T-16 ligar o papel Administrador, a obrigação vem desta porta. */
function exigirDoisFatores(): void
{
    app()->instance(ExigenciaDeDoisFatores::class, new class implements ExigenciaDeDoisFatores
    {
        public function obrigatorio(int $contaId): bool
        {
            return true;
        }
    });
}

/** Redefine a senha por e-mail há `$horas` e deixa um navegador novo pronto para entrar. */
function entrarDepoisDeRecuperar(int $horas): void
{
    test()->travel(-$horas)->hours();
    test()->postJson('/api/v1/auth/senha/esqueci', ['identificador' => 'maria@exemplo.test'])->assertNoContent();
    $token = collect(app(PostmarkFalso::class)->enviados)->last()->codigo;
    test()->postJson('/api/v1/auth/senha/redefinir', ['token' => $token, 'senha' => 'outra-senha-forte-456'])->assertNoContent();
    test()->travelBack();
    novoNavegador();
}
