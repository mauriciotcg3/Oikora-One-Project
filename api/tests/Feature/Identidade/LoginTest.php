<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Rules\Password;

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
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    test()->flushHeaders();
    test()->withCredentials();
    test()->withUnencryptedCookie(config('session.cookie'), $cookie);
}

it('entra com e-mail ou celular e cria a sessão da conta', function (string $identificador): void {
    $conta = criarConta();

    entrarCom($identificador)->assertNoContent();

    expect(DB::connection('pgsql_dono')->table('sessions')->where('user_id', $conta)->count())->toBe(1);
})->with(['maria@exemplo.test', '+5511987654321']);

it('responde igual para senha errada e conta inexistente', function (): void {
    criarConta();

    $senhaErrada = entrarCom('maria@exemplo.test', 'senha-errada-999')->assertUnprocessable();
    $semConta = entrarCom('ninguem@exemplo.test')->assertUnprocessable();

    expect($senhaErrada->json('message'))->toBe($semConta->json('message'))
        ->and($senhaErrada->json('errors'))->toBe($semConta->json('errors'));
    $this->assertGuest();
});

it('valida os campos e devolve request_id nos erros', function (): void {
    $resposta = $this->postJson(ROTA_LOGIN, [])->assertUnprocessable()->assertJsonValidationErrors(['identificador', 'senha']);

    expect($resposta->json('request_id'))->toBeString()->not->toBeEmpty()
        ->and($resposta->headers->get('X-Request-Id'))->toBe($resposta->json('request_id'));
});

it('bloqueia por identificador ao passar do limite, mesmo com a senha certa', function (): void {
    config(['oikora.login.tentativas_por_identificador' => 3]);
    criarConta();

    foreach (range(1, 3) as $tentativa) {
        entrarCom('maria@exemplo.test', 'senha-errada-999')->assertUnprocessable();
    }

    entrarCom('maria@exemplo.test')
        ->assertTooManyRequests()
        ->assertJsonPath('codigo', 'muitas_tentativas')
        ->assertHeader('Retry-After');
    $this->assertGuest();
});

it('bloqueia por IP ao passar do limite, trocando de identificador', function (): void {
    config(['oikora.login.tentativas_por_ip' => 3]);

    foreach (range(1, 3) as $tentativa) {
        entrarCom("pessoa{$tentativa}@exemplo.test")->assertUnprocessable();
    }

    entrarCom('outra@exemplo.test')->assertTooManyRequests()->assertJsonPath('codigo', 'muitas_tentativas');
});

it('aumenta o bloqueio a cada nova violação', function (): void {
    config(['oikora.login.tentativas_por_identificador' => 2, 'oikora.login.bloqueio_inicial_segundos' => 60]);

    $violar = function (): int {
        entrarCom('maria@exemplo.test', 'senha-errada-999');
        entrarCom('maria@exemplo.test', 'senha-errada-999');

        return (int) entrarCom('maria@exemplo.test')->assertTooManyRequests()->headers->get('Retry-After');
    };

    $primeiro = $violar();
    $this->travel($primeiro + 1)->seconds();
    $segundo = $violar();

    expect($segundo)->toBeGreaterThan($primeiro);
});

it('libera o identificador depois de um login com sucesso', function (): void {
    config(['oikora.login.tentativas_por_identificador' => 3]);
    criarConta();

    entrarCom('maria@exemplo.test', 'senha-errada-999');
    entrarCom('maria@exemplo.test', 'senha-errada-999');
    entrarCom('maria@exemplo.test')->assertNoContent();
    Auth::forgetGuards();

    entrarCom('maria@exemplo.test', 'senha-errada-999')->assertUnprocessable();
    entrarCom('maria@exemplo.test', 'senha-errada-999')->assertUnprocessable();
    entrarCom('maria@exemplo.test')->assertNoContent();
});

it('não abre sessão para quem tem 2FA: pede o código com o destino mascarado', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);

    $resposta = entrarCom('maria@exemplo.test')
        ->assertForbidden()
        ->assertJsonPath('codigo', 'dois_fatores_necessario')
        ->assertJsonPath('canal', 'email');

    expect($resposta->json('destino'))->not->toContain('maria')
        ->and(DB::connection('pgsql_dono')->table('sessions')->whereNotNull('user_id')->count())->toBe(0);
    $this->assertGuest();
});

it('recusa conta bloqueada só depois de conferir a senha', function (): void {
    criarConta(['estado' => 'bloqueada']);

    entrarCom('maria@exemplo.test', 'senha-errada-999')->assertUnprocessable();
    entrarCom('maria@exemplo.test')->assertForbidden()->assertJsonPath('codigo', 'conta_bloqueada');
    $this->assertGuest();
});

it('grava sucesso e falha em eventos_autenticacao sem dado em claro', function (): void {
    $conta = criarConta();

    entrarCom('maria@exemplo.test')->assertNoContent();
    Auth::forgetGuards();
    entrarCom('ninguem@exemplo.test', 'segredo-digitado')->assertUnprocessable();

    [$sucesso, $falha] = eventos();
    $conteudo = json_encode(eventos());

    expect($sucesso->evento)->toBe('auth.login.sucesso')
        ->and($sucesso->conta_id)->toBe($conta)
        ->and($falha->evento)->toBe('auth.login.falha')
        ->and($falha->conta_id)->toBeNull()
        ->and($falha->request_id)->not->toBeEmpty()
        ->and($conteudo)->not->toContain('ninguem')->not->toContain('maria')
        ->not->toContain('segredo-digitado')->not->toContain('127.0.0.1');
});

it('mantém a sessão por 30 dias, renovada com o uso', function (): void {
    criarConta();
    $cookie = cookieDaSessao(entrarCom('maria@exemplo.test')->assertNoContent());

    expect(config('session.lifetime'))->toBe(43200)
        ->and(config('session.expire_on_close'))->toBeFalse();

    $this->travel(29)->days();
    comSessao($cookie);
    $this->get('/sanctum/csrf-cookie')->assertNoContent();

    $this->travel(29)->days();
    comSessao($cookie);
    $this->postJson('/api/v1/auth/logout')->assertNoContent();
});

it('encerra a sessão depois de 30 dias sem uso', function (): void {
    criarConta();
    $cookie = cookieDaSessao(entrarCom('maria@exemplo.test')->assertNoContent());

    $this->travel(31)->days();
    comSessao($cookie);

    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('sai e invalida a sessão', function (): void {
    criarConta();
    $cookie = cookieDaSessao(entrarCom('maria@exemplo.test')->assertNoContent());

    comSessao($cookie);
    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    comSessao($cookie);
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('recusa senha com menos de 8 caracteres ou vazada', function (): void {
    $vazada = 'senha-vazada-123';
    $sufixo = strtoupper(substr(sha1($vazada), 5));
    Http::fake(['api.pwnedpasswords.com/*' => Http::response("{$sufixo}:42")]);

    $passa = fn (string $senha): bool => Validator::make(['senha' => $senha], ['senha' => Password::default()])->passes();

    expect($passa('curta12'))->toBeFalse()
        ->and($passa($vazada))->toBeFalse()
        ->and($passa('uma-senha-boa-456'))->toBeTrue();
});
