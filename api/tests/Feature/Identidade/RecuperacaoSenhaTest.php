<?php

use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

const ROTA_ESQUECI = '/api/v1/auth/senha/esqueci';
const ROTA_REDEFINIR = '/api/v1/auth/senha/redefinir';
const SENHA_NOVA = 'outra-senha-forte-456';
const SENHA_VAZADA = 'senha-vazada-123';

beforeEach(function (): void {
    // O HIBP falso conhece só a SENHA_VAZADA: as demais senhas dos testes passam.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr(strtoupper(sha1(SENHA_VAZADA)), 5).':42')]);
});

function esqueci(string $identificador = 'maria@exemplo.test'): TestResponse
{
    return test()->postJson(ROTA_ESQUECI, ['identificador' => $identificador]);
}

function redefinir(string $token, string $senha = SENHA_NOVA): TestResponse
{
    return test()->postJson(ROTA_REDEFINIR, ['token' => $token, 'senha' => $senha]);
}

function contaVerificada(array $dados = []): int
{
    return criarConta(['email_verificado_em' => now(), 'celular_verificado_em' => now(), ...$dados]);
}

function tokenPorEmail(): string
{
    return collect(app(PostmarkFalso::class)->enviados)->last()->codigo;
}

it('manda o token por e-mail quando o pedido vem pelo e-mail verificado', function (): void {
    contaVerificada();

    esqueci()->assertNoContent();

    expect(app(PostmarkFalso::class)->enviados)->toHaveCount(1)
        ->and(strlen(tokenPorEmail()))->toBeGreaterThanOrEqual(32);
});

it('manda o token por WhatsApp quando o pedido vem pelo celular verificado', function (): void {
    contaVerificada();

    esqueci('+5511987654321')->assertNoContent();

    expect(app(MetaWhatsAppFalso::class)->enviados)->toHaveCount(1);
});

it('responde igual e não manda nada para conta inexistente ou identificador sem verificar', function (string $identificador): void {
    criarConta(['celular_verificado_em' => now()]);

    esqueci($identificador)->assertNoContent();

    expect(app(PostmarkFalso::class)->enviados)->toBeEmpty()
        ->and(app(MetaWhatsAppFalso::class)->enviados)->toBeEmpty();
})->with(['ninguem@exemplo.test', 'maria@exemplo.test']);

it('limita os pedidos sem revelar se a conta existe', function (string $identificador): void {
    contaVerificada();

    foreach (range(1, 5) as $_) {
        esqueci($identificador)->assertNoContent();
    }

    esqueci($identificador)->assertTooManyRequests()->assertJsonPath('codigo', 'muitas_tentativas');
})->with(['maria@exemplo.test', 'ninguem@exemplo.test']);

it('redefine a senha: a nova entra e a antiga não', function (): void {
    contaVerificada();
    esqueci();

    redefinir(tokenPorEmail())->assertNoContent();

    Auth::forgetGuards();
    entrarCom('maria@exemplo.test')->assertUnprocessable();
    entrarCom('maria@exemplo.test', SENHA_NOVA)->assertNoContent();
});

it('encerra todas as sessões da conta ao redefinir', function (): void {
    $conta = contaVerificada();
    $primeira = cookieDaSessao(entrarCom('maria@exemplo.test')->assertNoContent());
    novoNavegador();
    entrarCom('maria@exemplo.test')->assertNoContent();

    $sessoes = DB::connection('pgsql_dono')->table('sessions')->where('user_id', $conta);
    expect($sessoes->count())->toBeGreaterThan(0);

    // O pedido sai de um navegador já logado: essa sessão também precisa acabar.
    comSessao($primeira);
    esqueci();
    comSessao($primeira);
    redefinir(tokenPorEmail())->assertNoContent();

    expect($sessoes->count())->toBe(0);
});

it('aceita o token uma vez só', function (): void {
    contaVerificada();
    esqueci();
    $token = tokenPorEmail();

    redefinir($token)->assertNoContent();

    redefinir($token, 'mais-uma-senha-789')->assertUnprocessable()->assertJsonValidationErrors(['token']);
});

it('recusa o token depois de 60 minutos', function (): void {
    contaVerificada();
    esqueci();

    $this->travel(61)->minutes();

    redefinir(tokenPorEmail())->assertUnprocessable()->assertJsonValidationErrors(['token']);
});

it('o pedido novo invalida o token anterior', function (): void {
    contaVerificada();
    esqueci();
    $anterior = tokenPorEmail();
    esqueci();

    redefinir($anterior)->assertUnprocessable();
    redefinir(tokenPorEmail())->assertNoContent();
});

it('recusa token inventado', function (): void {
    contaVerificada();

    redefinir(str_repeat('a', 64))->assertUnprocessable()->assertJsonValidationErrors(['token']);
});

it('recusa senha curta sem gastar o token', function (): void {
    contaVerificada();
    esqueci();

    redefinir(tokenPorEmail(), 'curta')->assertUnprocessable()->assertJsonValidationErrors(['senha']);
    redefinir(tokenPorEmail())->assertNoContent();
});

it('recusa senha vazada', function (): void {
    contaVerificada();
    esqueci();

    redefinir(tokenPorEmail(), SENHA_VAZADA)->assertUnprocessable()->assertJsonValidationErrors(['senha']);
});

it('redefinir não dispensa o 2FA no próximo acesso', function (): void {
    contaVerificada(['dois_fatores_canal' => 'email']);
    esqueci();
    redefinir(tokenPorEmail())->assertNoContent();

    Auth::forgetGuards();
    entrarCom('maria@exemplo.test', SENHA_NOVA)->assertForbidden()->assertJsonPath('codigo', 'dois_fatores_necessario');
    $this->assertGuest();
});

it('redefinir não desbloqueia a conta', function (): void {
    contaVerificada(['estado' => 'bloqueada']);
    esqueci();
    redefinir(tokenPorEmail())->assertNoContent();

    Auth::forgetGuards();
    entrarCom('maria@exemplo.test', SENHA_NOVA)->assertForbidden()->assertJsonPath('codigo', 'conta_bloqueada');
});

it('grava pedido e redefinição em eventos_autenticacao sem dado em claro', function (): void {
    $conta = contaVerificada();
    esqueci();
    $token = tokenPorEmail();
    redefinir($token)->assertNoContent();

    $registrados = collect(eventos())->keyBy('evento');
    $conteudo = json_encode(eventos());

    expect($registrados['auth.senha.recuperacao_pedida']->conta_id)->toBe($conta)
        ->and($registrados['auth.senha.recuperacao_pedida']->canal)->toBe('email')
        ->and($registrados['auth.senha.redefinida']->conta_id)->toBe($conta)
        ->and($conteudo)->not->toContain($token)->not->toContain('maria');
});
