<?php

use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use Illuminate\Support\Facades\DB;

function verificacao(string $tipo): string
{
    return '/api/v1/conta/verificacao/'.$tipo;
}

function contaLida(int $id): object
{
    return DB::connection('pgsql_dono')->table('contas')->find($id);
}

function codigoDeEmail(): string
{
    return collect(app(PostmarkFalso::class)->enviados)->last()->codigo;
}

function contaSemVerificar(array $dados = []): int
{
    return criarConta($dados);
}

it('exige login', function (): void {
    test()->postJson(verificacao('email'))->assertUnauthorized();
    test()->postJson(verificacao('email').'/confirmar', ['codigo' => '123456'])->assertUnauthorized();
});

it('confirmar o código marca email_verificado_em', function (): void {
    $conta = contaSemVerificar();
    $cookie = logarNaConta();

    test()->postJson(verificacao('email'))->assertNoContent();

    comSessao($cookie);
    test()->postJson(verificacao('email').'/confirmar', ['codigo' => codigoDeEmail()])->assertNoContent();

    expect(contaLida($conta)->email_verificado_em)->not->toBeNull()
        ->and(contaLida($conta)->celular_verificado_em)->toBeNull();
});

it('confirmar o código marca celular_verificado_em, pelo WhatsApp', function (): void {
    $conta = contaSemVerificar();
    $cookie = logarNaConta();

    test()->postJson(verificacao('celular'))->assertNoContent();

    comSessao($cookie);
    test()->postJson(verificacao('celular').'/confirmar', ['codigo' => app(MetaWhatsAppFalso::class)->enviados[0]->codigo])->assertNoContent();

    expect(contaLida($conta)->celular_verificado_em)->not->toBeNull()
        ->and(contaLida($conta)->email_verificado_em)->toBeNull();
});

it('recusa código errado sem marcar nada', function (): void {
    $conta = contaSemVerificar();
    $cookie = logarNaConta();
    test()->postJson(verificacao('email'))->assertNoContent();
    $errado = codigoDeEmail() === '000000' ? '111111' : '000000';

    comSessao($cookie);
    test()->postJson(verificacao('email').'/confirmar', ['codigo' => $errado])->assertUnprocessable()->assertJsonValidationErrors(['codigo']);

    expect(contaLida($conta)->email_verificado_em)->toBeNull();
});

it('recusa o código depois de 10 minutos', function (): void {
    $conta = contaSemVerificar();
    $cookie = logarNaConta();
    test()->postJson(verificacao('email'))->assertNoContent();

    $this->travel(11)->minutes();
    comSessao($cookie);
    test()->postJson(verificacao('email').'/confirmar', ['codigo' => codigoDeEmail()])->assertUnprocessable();

    expect(contaLida($conta)->email_verificado_em)->toBeNull();
});

it('o código de e-mail não serve para o celular', function (): void {
    $conta = contaSemVerificar();
    $cookie = logarNaConta();
    test()->postJson(verificacao('email'))->assertNoContent();

    comSessao($cookie);
    test()->postJson(verificacao('celular').'/confirmar', ['codigo' => codigoDeEmail()])->assertUnprocessable();

    expect(contaLida($conta)->celular_verificado_em)->toBeNull();
});

it('invalida o código depois de 5 erros', function (): void {
    contaSemVerificar();
    $cookie = logarNaConta();
    test()->postJson(verificacao('email'))->assertNoContent();
    $errado = codigoDeEmail() === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $_) {
        comSessao($cookie);
        test()->postJson(verificacao('email').'/confirmar', ['codigo' => $errado])->assertUnprocessable();
    }

    comSessao($cookie);
    test()->postJson(verificacao('email').'/confirmar', ['codigo' => codigoDeEmail()])->assertTooManyRequests();
});

it('não manda código para contato já verificado', function (): void {
    contaSemVerificar(['email_verificado_em' => now()]);
    logarNaConta();

    test()->postJson(verificacao('email'))->assertNoContent();

    expect(app(PostmarkFalso::class)->enviados)->toBeEmpty();
});

it('recusa contato que a conta não tem', function (): void {
    contaSemVerificar(['celular' => null]);
    logarNaConta();

    test()->postJson(verificacao('celular'))->assertUnprocessable();
});

it('recusa tipo desconhecido', function (): void {
    contaSemVerificar();
    logarNaConta();

    test()->postJson(verificacao('telefone'))->assertNotFound();
});

it('limita os pedidos de código', function (): void {
    contaSemVerificar();
    $cookie = logarNaConta();

    foreach (range(1, 5) as $_) {
        comSessao($cookie);
        test()->postJson(verificacao('email'))->assertNoContent();
    }

    comSessao($cookie);
    test()->postJson(verificacao('email'))->assertTooManyRequests()->assertJsonPath('codigo', 'muitas_tentativas');
});

it('grava a verificação em eventos_autenticacao sem dado em claro', function (): void {
    contaSemVerificar();
    $cookie = logarNaConta();
    test()->postJson(verificacao('email'))->assertNoContent();
    $codigo = codigoDeEmail();

    comSessao($cookie);
    test()->postJson(verificacao('email').'/confirmar', ['codigo' => $codigo])->assertNoContent();

    expect(collect(eventos())->pluck('evento')->all())->toContain('auth.contato.verificado')
        ->and(json_encode(eventos()))->not->toContain($codigo)->not->toContain('maria');
});
