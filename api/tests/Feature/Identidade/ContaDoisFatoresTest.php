<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;

const ROTA_CONTA_2FA = '/api/v1/conta/2fa';
const ROTA_CONFIRMAR_SENHA = '/api/v1/conta/confirmar-senha';
const ROTA_CONTA_SENHA = '/api/v1/conta/senha';

beforeEach(function (): void {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
});

function linhaDaConta(int $id): object
{
    return DB::connection('pgsql_dono')->table('contas')->find($id);
}

it('confirma a senha certa e recusa a errada', function (): void {
    $cookie = logado();

    test()->postJson(ROTA_CONFIRMAR_SENHA, ['senha' => 'senha-errada-999'])->assertUnprocessable()->assertJsonValidationErrors(['senha']);

    comSessao($cookie);
    test()->postJson(ROTA_CONFIRMAR_SENHA, ['senha' => SENHA])->assertNoContent();
});

it('limita as tentativas de confirmar a senha', function (): void {
    $cookie = logado();

    foreach (range(1, 5) as $_) {
        comSessao($cookie);
        test()->postJson(ROTA_CONFIRMAR_SENHA, ['senha' => 'senha-errada-999'])->assertUnprocessable();
    }

    comSessao($cookie);
    test()->postJson(ROTA_CONFIRMAR_SENHA, ['senha' => SENHA])->assertTooManyRequests()->assertJsonPath('codigo', 'muitas_tentativas');
});

it('exige login nas rotas da conta', function (string $metodo, string $rota): void {
    test()->json($metodo, $rota)->assertUnauthorized();
})->with([
    ['GET', ROTA_CONTA_2FA],
    ['POST', ROTA_CONTA_2FA],
    ['DELETE', ROTA_CONTA_2FA],
    ['PUT', ROTA_CONTA_SENHA],
    ['POST', ROTA_CONFIRMAR_SENHA],
]);

it('recusa mudar 2FA ou senha sem confirmar a senha', function (string $metodo, string $rota, array $corpo): void {
    logado();

    test()->json($metodo, $rota, $corpo)->assertForbidden()->assertJsonPath('codigo', 'senha_nao_confirmada');
})->with([
    ['POST', ROTA_CONTA_2FA, ['canal' => 'email']],
    ['DELETE', ROTA_CONTA_2FA, []],
    ['PUT', ROTA_CONTA_SENHA, ['senha' => 'outra-senha-forte-456']],
]);

it('a confirmação da senha vale 15 minutos', function (): void {
    $cookie = logado();
    confirmarSenhaAtual($cookie);

    $this->travel(16)->minutes();
    comSessao($cookie);

    test()->postJson(ROTA_CONTA_2FA, ['canal' => 'email'])->assertForbidden()->assertJsonPath('codigo', 'senha_nao_confirmada');
});

it('ativa o 2FA por e-mail e gera 10 códigos de recuperação únicos', function (): void {
    $cookie = logado();
    confirmarSenhaAtual($cookie);

    $resposta = test()->postJson(ROTA_CONTA_2FA, ['canal' => 'email'])->assertOk()->assertJsonPath('canal', 'email');
    $codigos = $resposta->json('codigos_recuperacao');

    expect($codigos)->toHaveCount(10)
        ->and(array_unique($codigos))->toHaveCount(10)
        ->and($codigos[0])->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
});

it('recusa ativar por canal sem contato verificado', function (): void {
    $cookie = logado(['email_verificado_em' => null]);
    confirmarSenhaAtual($cookie);

    test()->postJson(ROTA_CONTA_2FA, ['canal' => 'email'])->assertUnprocessable()->assertJsonValidationErrors(['canal']);
});

it('mostra o estado do 2FA sem revelar segredo nem códigos', function (): void {
    $cookie = logado();
    ativarDoisFatores($cookie);

    comSessao($cookie);
    $resposta = test()->getJson(ROTA_CONTA_2FA)->assertOk()->assertExactJson(['canal' => 'email', 'recuperacao_restantes' => 10]);

    expect($resposta->getContent())->not->toContain('segredo');
});

it('ativa o TOTP só depois de conferir um código do aplicativo', function (): void {
    $conta = criarContaVerificada();
    $cookie = logarNaConta();
    confirmarSenhaAtual($cookie);

    $segredo = test()->postJson(ROTA_CONTA_2FA, ['canal' => 'totp'])->assertOk()->assertJsonStructure(['segredo', 'otpauth_url'])->json('segredo');
    expect(linhaDaConta($conta)->dois_fatores_canal)->toBeNull();

    comSessao($cookie);
    test()->postJson(ROTA_CONTA_2FA, ['canal' => 'totp', 'codigo' => '000000'])->assertUnprocessable()->assertJsonValidationErrors(['codigo']);

    comSessao($cookie);
    $resposta = test()->postJson(ROTA_CONTA_2FA, ['canal' => 'totp', 'codigo' => app(Google2FA::class)->getCurrentOtp($segredo)])->assertOk();

    expect($resposta->json('codigos_recuperacao'))->toHaveCount(10)
        ->and(linhaDaConta($conta)->dois_fatores_canal)->toBe('totp')
        ->and(linhaDaConta($conta)->two_factor_confirmed_at)->not->toBeNull();
});

it('não guarda segredo nem códigos de recuperação em claro', function (): void {
    $conta = criarContaVerificada();
    $cookie = logarNaConta();
    confirmarSenhaAtual($cookie);

    $segredo = test()->postJson(ROTA_CONTA_2FA, ['canal' => 'totp'])->json('segredo');
    comSessao($cookie);
    $codigos = test()->postJson(ROTA_CONTA_2FA, ['canal' => 'totp', 'codigo' => app(Google2FA::class)->getCurrentOtp($segredo)])->json('codigos_recuperacao');

    $linha = linhaDaConta($conta);

    expect($linha->two_factor_secret)->not->toContain($segredo)
        ->and($linha->two_factor_recovery_codes)->not->toContain($codigos[0]);
});

it('desativa o 2FA e o próximo login volta a ser só com senha', function (): void {
    $conta = criarContaVerificada();
    $cookie = logarNaConta();
    ativarDoisFatores($cookie);

    comSessao($cookie);
    test()->deleteJson(ROTA_CONTA_2FA)->assertNoContent();

    expect(linhaDaConta($conta)->dois_fatores_canal)->toBeNull()
        ->and(linhaDaConta($conta)->two_factor_recovery_codes)->toBeNull();

    novoNavegador();
    entrarCom('maria@exemplo.test')->assertNoContent();
});

it('não deixa desativar o 2FA de quem é obrigado a ter', function (): void {
    exigirDoisFatores();
    $cookie = logado();
    ativarDoisFatores($cookie);

    comSessao($cookie);
    test()->deleteJson(ROTA_CONTA_2FA)->assertForbidden()->assertJsonPath('codigo', 'dois_fatores_obrigatorio');
});

it('trocar a senha encerra todas as sessões da conta', function (): void {
    $conta = criarContaVerificada();
    logarNaConta();
    novoNavegador();
    $cookie = logarNaConta();
    confirmarSenhaAtual($cookie);

    comSessao($cookie);
    test()->putJson(ROTA_CONTA_SENHA, ['senha' => 'outra-senha-forte-456'])->assertNoContent();

    expect(DB::connection('pgsql_dono')->table('sessions')->where('user_id', $conta)->count())->toBe(0);

    novoNavegador();
    entrarCom('maria@exemplo.test')->assertUnprocessable();
    novoNavegador();
    entrarCom('maria@exemplo.test', 'outra-senha-forte-456')->assertNoContent();
});

it('recusa senha curta ao trocar', function (): void {
    $cookie = logado();
    confirmarSenhaAtual($cookie);

    test()->putJson(ROTA_CONTA_SENHA, ['senha' => 'curta'])->assertUnprocessable()->assertJsonValidationErrors(['senha']);
});

it('grava ativação, desativação e troca de senha em eventos_autenticacao', function (): void {
    $cookie = logado();
    ativarDoisFatores($cookie);

    comSessao($cookie);
    test()->deleteJson(ROTA_CONTA_2FA)->assertNoContent();

    comSessao($cookie);
    test()->putJson(ROTA_CONTA_SENHA, ['senha' => 'outra-senha-forte-456'])->assertNoContent();

    expect(collect(eventos())->pluck('evento')->all())
        ->toContain('auth.2fa.ativado', 'auth.2fa.desativado', 'auth.senha.trocada');
});
