<?php

use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;

const ROTA_DESAFIO = '/api/v1/auth/2fa/confirmar';

beforeEach(function (): void {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
});

function contaComTotp(): string
{
    $segredo = app(Google2FA::class)->generateSecretKey();

    criarContaVerificada([
        'dois_fatores_canal' => 'totp',
        'two_factor_secret' => Crypt::encryptString($segredo),
        'two_factor_confirmed_at' => now(),
    ]);

    return $segredo;
}

function abrirDesafio(): string
{
    $cookie = cookieDaSessao(entrarCom('maria@exemplo.test')->assertForbidden());
    comSessao($cookie);

    return $cookie;
}

/** Ativa o 2FA por e-mail, sai e devolve os códigos de recuperação. */
function codigosDeRecuperacao(): array
{
    criarContaVerificada();
    $cookie = logarNaConta();
    $codigos = ativarDoisFatores($cookie);
    novoNavegador();

    return $codigos;
}

function autenticadas(): int
{
    return DB::connection('pgsql_dono')->table('sessions')->whereNotNull('user_id')->count();
}

function recuperacaoPorEmailHa(int $horas): void
{
    criarContaVerificada(['dois_fatores_canal' => 'email']);
    entrarDepoisDeRecuperar($horas);
}

it('completa o desafio com TOTP válido', function (): void {
    $segredo = contaComTotp();
    abrirDesafio();

    test()->postJson(ROTA_DESAFIO, ['codigo' => app(Google2FA::class)->getCurrentOtp($segredo)])->assertNoContent();

    expect(autenticadas())->toBe(1)
        ->and(app(PostmarkFalso::class)->enviados)->toBeEmpty();
});

it('recusa TOTP errado e o mesmo TOTP usado duas vezes', function (): void {
    $segredo = contaComTotp();
    $otp = app(Google2FA::class)->getCurrentOtp($segredo);
    $cookie = abrirDesafio();

    test()->postJson(ROTA_DESAFIO, ['codigo' => $otp === '000000' ? '111111' : '000000'])->assertUnprocessable();
    expect(autenticadas())->toBe(0);

    comSessao($cookie);
    test()->postJson(ROTA_DESAFIO, ['codigo' => $otp])->assertNoContent();

    DB::connection('pgsql_dono')->table('sessions')->delete();
    novoNavegador();
    abrirDesafio();

    test()->postJson(ROTA_DESAFIO, ['codigo' => $otp])->assertUnprocessable();
    expect(autenticadas())->toBe(0);
});

it('limita os erros de TOTP', function (): void {
    $segredo = contaComTotp();
    $cookie = abrirDesafio();
    $otp = app(Google2FA::class)->getCurrentOtp($segredo);
    $errado = $otp === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $_) {
        comSessao($cookie);
        test()->postJson(ROTA_DESAFIO, ['codigo' => $errado])->assertUnprocessable();
    }

    comSessao($cookie);
    test()->postJson(ROTA_DESAFIO, ['codigo' => $otp])->assertTooManyRequests()->assertJsonPath('codigo', 'muitas_tentativas');
});

it('conta com TOTP não pede código por mensagem', function (): void {
    contaComTotp();
    abrirDesafio();

    test()->postJson('/api/v1/auth/2fa/enviar', ['canal' => 'email'])->assertUnprocessable()->assertJsonValidationErrors(['canal']);

    expect(app(PostmarkFalso::class)->enviados)->toBeEmpty();
});

it('o código de recuperação completa o desafio uma vez só', function (): void {
    $codigos = codigosDeRecuperacao();

    abrirDesafio();
    test()->postJson(ROTA_DESAFIO, ['codigo_recuperacao' => $codigos[0]])->assertNoContent();

    DB::connection('pgsql_dono')->table('sessions')->delete();
    novoNavegador();
    abrirDesafio();

    test()->postJson(ROTA_DESAFIO, ['codigo_recuperacao' => $codigos[0]])->assertUnprocessable()->assertJsonValidationErrors(['codigo_recuperacao']);
    expect(autenticadas())->toBe(0);
});

it('aceita o código de recuperação sem hífen e em minúsculas', function (): void {
    $codigos = codigosDeRecuperacao();

    abrirDesafio();

    test()->postJson(ROTA_DESAFIO, ['codigo_recuperacao' => strtolower(str_replace('-', '', $codigos[1]))])->assertNoContent();
});

it('grava o uso do código de recuperação sem o código em claro', function (): void {
    $codigos = codigosDeRecuperacao();

    abrirDesafio();
    test()->postJson(ROTA_DESAFIO, ['codigo_recuperacao' => $codigos[2]])->assertNoContent();

    expect(collect(eventos())->where('evento', 'auth.2fa.sucesso')->last()->canal)->toBe('recuperacao')
        ->and(json_encode(eventos()))->not->toContain($codigos[2]);
});

it('com 2FA obrigatório e recuperação por e-mail há menos de 24h, o código não vai por e-mail', function (): void {
    exigirDoisFatores();
    recuperacaoPorEmailHa(23);

    entrarCom('maria@exemplo.test', 'outra-senha-forte-456')->assertForbidden()->assertJsonPath('canal', 'whatsapp');

    expect(app(PostmarkFalso::class)->enviados)->toHaveCount(1)
        ->and(app(MetaWhatsAppFalso::class)->enviados)->toHaveCount(1);
});

it('com a mesma regra, recusa reenviar pelo canal da recuperação', function (): void {
    exigirDoisFatores();
    recuperacaoPorEmailHa(23);

    comSessao(cookieDaSessao(entrarCom('maria@exemplo.test', 'outra-senha-forte-456')->assertForbidden()));

    test()->postJson('/api/v1/auth/2fa/enviar', ['canal' => 'email'])->assertUnprocessable()->assertJsonValidationErrors(['canal']);
});

it('depois de 24h o canal da recuperação volta a valer', function (): void {
    exigirDoisFatores();
    recuperacaoPorEmailHa(25);

    entrarCom('maria@exemplo.test', 'outra-senha-forte-456')->assertForbidden()->assertJsonPath('canal', 'email');
});

it('sem 2FA obrigatório a regra das 24h não se aplica', function (): void {
    recuperacaoPorEmailHa(1);

    entrarCom('maria@exemplo.test', 'outra-senha-forte-456')->assertForbidden()->assertJsonPath('canal', 'email');
});

it('sem outro canal, recusa em vez de usar o canal da recuperação', function (): void {
    exigirDoisFatores();
    criarContaVerificada(['dois_fatores_canal' => 'email', 'celular' => null]);
    entrarDepoisDeRecuperar(1);

    entrarCom('maria@exemplo.test', 'outra-senha-forte-456')->assertForbidden()->assertJsonPath('codigo', 'canal_indisponivel');

    expect(app(PostmarkFalso::class)->enviados)->toHaveCount(1);
    test()->assertGuest();
});
