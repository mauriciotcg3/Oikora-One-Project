<?php

use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

const ROTA_CONFIRMAR = '/api/v1/auth/2fa/confirmar';
const ROTA_ENVIAR = '/api/v1/auth/2fa/enviar';

/** Faz o login de uma conta com 2FA e devolve o cookie da sessão ainda sem conta. */
function iniciarDesafio(string $identificador = 'maria@exemplo.test'): string
{
    $cookie = cookieDaSessao(entrarCom($identificador)->assertForbidden());
    comSessao($cookie);

    return $cookie;
}

function ultimoCodigoPorEmail(): string
{
    return collect(app(PostmarkFalso::class)->enviados)->last()->codigo;
}

function confirmar(string $codigo): TestResponse
{
    return test()->postJson(ROTA_CONFIRMAR, ['codigo' => $codigo]);
}

function codigoErrado(): string
{
    return ultimoCodigoPorEmail() === '000000' ? '111111' : '000000';
}

function sessoesAutenticadas(): int
{
    return DB::connection('pgsql_dono')->table('sessions')->whereNotNull('user_id')->count();
}

it('manda o primeiro código no login, sem abrir sessão', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);

    entrarCom('maria@exemplo.test')->assertForbidden()->assertJsonPath('codigo', 'dois_fatores_necessario');

    expect(app(PostmarkFalso::class)->enviados)->toHaveCount(1)
        ->and(ultimoCodigoPorEmail())->toMatch('/^\d{6}$/')
        ->and(sessoesAutenticadas())->toBe(0);
    $this->assertGuest();
});

it('não manda código quando a senha está errada', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);

    entrarCom('maria@exemplo.test', 'senha-errada-999')->assertUnprocessable();

    expect(app(PostmarkFalso::class)->enviados)->toBeEmpty();
});

it('abre a sessão só no confirmar, com id de sessão novo', function (): void {
    $conta = criarConta(['dois_fatores_canal' => 'email']);
    $cookie = iniciarDesafio();

    $resposta = confirmar(ultimoCodigoPorEmail())->assertNoContent();

    expect(cookieDaSessao($resposta))->not->toBe($cookie)
        ->and(DB::connection('pgsql_dono')->table('sessions')->where('user_id', $conta)->count())->toBe(1);
});

it('recusa código errado com 422 e mantém o desafio', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);
    $cookie = iniciarDesafio();

    confirmar(codigoErrado())->assertUnprocessable()->assertJsonValidationErrors(['codigo']);
    expect(sessoesAutenticadas())->toBe(0);

    comSessao($cookie);
    confirmar(ultimoCodigoPorEmail())->assertNoContent();
});

it('recusa o código depois de 10 minutos', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);
    iniciarDesafio();

    $this->travel(11)->minutes();

    confirmar(ultimoCodigoPorEmail())->assertUnprocessable();
    expect(sessoesAutenticadas())->toBe(0);
});

it('aceita o código só uma vez', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);
    iniciarDesafio();
    $usado = ultimoCodigoPorEmail();
    confirmar($usado)->assertNoContent();

    DB::connection('pgsql_dono')->table('sessions')->delete();
    novoNavegador();
    iniciarDesafio();

    confirmar($usado === ultimoCodigoPorEmail() ? codigoErrado() : $usado)->assertUnprocessable();
    expect(sessoesAutenticadas())->toBe(0);
});

it('invalida o código depois de 5 erros, mesmo que o certo venha depois', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);
    $cookie = iniciarDesafio();

    foreach (range(1, 5) as $_) {
        comSessao($cookie);
        confirmar(codigoErrado())->assertUnprocessable();
    }

    comSessao($cookie);
    confirmar(ultimoCodigoPorEmail())->assertTooManyRequests()->assertJsonPath('codigo', 'muitas_tentativas');
    expect(sessoesAutenticadas())->toBe(0);
});

it('recusa confirmar e enviar sem desafio aberto', function (): void {
    $this->postJson(ROTA_CONFIRMAR, ['codigo' => '123456'])->assertUnauthorized();
    $this->postJson(ROTA_ENVIAR, ['canal' => 'email'])->assertUnauthorized();
});

it('recusa o desafio de quem foi bloqueado depois da senha', function (): void {
    $conta = criarConta(['dois_fatores_canal' => 'email']);
    iniciarDesafio();

    DB::connection('pgsql_dono')->table('contas')->where('id', $conta)->update(['estado' => 'bloqueada']);

    confirmar(ultimoCodigoPorEmail())->assertForbidden()->assertJsonPath('codigo', 'conta_bloqueada');
    expect(sessoesAutenticadas())->toBe(0);
});

it('valida o formato do código', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);
    iniciarDesafio();

    confirmar('12ab')->assertUnprocessable()->assertJsonValidationErrors(['codigo']);
});

it('reenvia por outro canal, com destino mascarado, e o código anterior deixa de valer', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);
    $cookie = iniciarDesafio();
    $anterior = ultimoCodigoPorEmail();

    $this->postJson(ROTA_ENVIAR, ['canal' => 'whatsapp'])
        ->assertOk()
        ->assertExactJson(['canal' => 'whatsapp', 'destino' => '(11) •••••-4321']);

    $novo = app(MetaWhatsAppFalso::class)->enviados[0]->codigo;

    comSessao($cookie);
    confirmar($anterior === $novo ? codigoErrado() : $anterior)->assertUnprocessable();

    comSessao($cookie);
    confirmar($novo)->assertNoContent();
});

it('recusa canal sem contato na conta', function (): void {
    criarConta(['dois_fatores_canal' => 'email', 'celular' => null]);
    iniciarDesafio();

    $this->postJson(ROTA_ENVIAR, ['canal' => 'whatsapp'])->assertUnprocessable()->assertJsonValidationErrors(['canal']);
});

it('limita os reenvios do mesmo desafio', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);
    $cookie = iniciarDesafio();

    foreach (range(1, 4) as $_) {
        comSessao($cookie);
        $this->postJson(ROTA_ENVIAR, ['canal' => 'email'])->assertOk();
    }

    comSessao($cookie);
    $this->postJson(ROTA_ENVIAR, ['canal' => 'email'])->assertTooManyRequests();

    expect(app(PostmarkFalso::class)->enviados)->toHaveCount(5);
});

it('não manda mensagem para conta com TOTP, que fica fora deste recorte', function (): void {
    criarConta(['dois_fatores_canal' => 'totp']);

    entrarCom('maria@exemplo.test')->assertForbidden()->assertJsonPath('canal', 'totp');

    expect(app(PostmarkFalso::class)->enviados)->toBeEmpty()
        ->and(app(MetaWhatsAppFalso::class)->enviados)->toBeEmpty();
});

it('não aceita desafio antigo: depois de 30 minutos é preciso entrar de novo', function (): void {
    criarConta(['dois_fatores_canal' => 'email']);
    iniciarDesafio();

    $this->travel(31)->minutes();

    $this->postJson(ROTA_ENVIAR, ['canal' => 'email'])->assertUnauthorized();
});

it('não deixa conta com TOTP cair para código por mensagem', function (): void {
    criarConta(['dois_fatores_canal' => 'totp']);
    iniciarDesafio();

    $this->postJson(ROTA_ENVIAR, ['canal' => 'email'])->assertUnauthorized();

    expect(app(PostmarkFalso::class)->enviados)->toBeEmpty();
});

it('não reenvia código para quem foi bloqueado depois da senha', function (): void {
    $conta = criarConta(['dois_fatores_canal' => 'email']);
    iniciarDesafio();

    DB::connection('pgsql_dono')->table('contas')->where('id', $conta)->update(['estado' => 'bloqueada']);

    $this->postJson(ROTA_ENVIAR, ['canal' => 'email'])->assertForbidden()->assertJsonPath('codigo', 'conta_bloqueada');

    expect(app(PostmarkFalso::class)->enviados)->toHaveCount(1);
});

it('grava sucesso e falha do desafio em eventos_autenticacao sem dado em claro', function (): void {
    $conta = criarConta(['dois_fatores_canal' => 'email']);
    $cookie = iniciarDesafio();
    $errado = codigoErrado();

    confirmar($errado)->assertUnprocessable();
    comSessao($cookie);
    $certo = ultimoCodigoPorEmail();
    confirmar($certo)->assertNoContent();

    $registrados = collect(eventos())->keyBy('evento');
    $conteudo = json_encode(eventos());

    expect($registrados['auth.2fa.falha']->conta_id)->toBe($conta)
        ->and($registrados['auth.2fa.falha']->canal)->toBe('email')
        ->and($registrados['auth.2fa.sucesso']->conta_id)->toBe($conta)
        ->and($conteudo)->not->toContain($certo)->not->toContain($errado)->not->toContain('maria');
});
