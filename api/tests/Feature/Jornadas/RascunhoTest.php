<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Jornadas\Infrastructure\Jobs\ExpirarRascunhosJornada;
use Illuminate\Support\Facades\DB;

function contextoDeJornada(): array
{
    $organizacao = criarOrganizacao();
    $igreja = criarIgreja($organizacao);
    $ator = entrarNoPainel($organizacao, $igreja, [Permissao::AvisosWhatsappGerir]);

    return [$organizacao, $igreja, $ator];
}

it('salva e retoma somente o rascunho da conta e organização atuais', function (): void {
    [$organizacao, $igreja, $ator] = contextoDeJornada();

    $salvo = test()->putJson('/api/v1/painel/rascunhos/conectar_whatsapp', [
        'etapa' => 'numero',
        'dados' => ['nome_exibicao' => 'Secretaria', 'numero_telefone' => '+5511999999999'],
        'versao' => 0,
    ])->assertOk()->json('data');

    expect($salvo)->toMatchArray(['etapa' => 'numero', 'versao' => 1])
        ->and($salvo['dados'])->toBe(['nome_exibicao' => 'Secretaria', 'numero_telefone' => '+5511999999999']);

    test()->getJson('/api/v1/painel/rascunhos/conectar_whatsapp')
        ->assertOk()->assertJsonPath('data.etapa', 'numero');

    $outraOrganizacao = criarOrganizacao('Outra');
    $outraIgreja = criarIgreja($outraOrganizacao);
    entrarNoPainel($outraOrganizacao, $outraIgreja, [Permissao::AvisosWhatsappGerir]);
    test()->getJson('/api/v1/painel/rascunhos/conectar_whatsapp')->assertOk()->assertJsonPath('data', null);

    entrarNoPainel($organizacao, $igreja, [Permissao::AvisosWhatsappGerir]);
    test()->getJson('/api/v1/painel/rascunhos/conectar_whatsapp')->assertOk()->assertJsonPath('data', null);
});

it('recusa versão obsoleta com 409 e devolve o estado atual', function (): void {
    contextoDeJornada();
    test()->putJson('/api/v1/painel/rascunhos/conectar_whatsapp', [
        'etapa' => 'inicio', 'dados' => ['nome_exibicao' => 'A'], 'versao' => 0,
    ])->assertOk();

    test()->putJson('/api/v1/painel/rascunhos/conectar_whatsapp', [
        'etapa' => 'fim', 'dados' => ['nome_exibicao' => 'B'], 'versao' => 0,
    ])->assertStatus(409)
        ->assertJsonPath('codigo', 'rascunho_desatualizado')
        ->assertJsonPath('atual.versao', 1);
});

it('recusa segredos e cifra campos sensíveis', function (): void {
    contextoDeJornada();
    test()->putJson('/api/v1/painel/rascunhos/conectar_whatsapp', [
        'etapa' => 'inicio', 'dados' => ['access_token' => 'segredo'], 'versao' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors('dados.access_token');

    test()->putJson('/api/v1/painel/rascunhos/conectar_whatsapp', [
        'etapa' => 'numero', 'dados' => ['numero_telefone' => '+5511888888888'], 'versao' => 0,
    ])->assertOk();

    $linha = DB::connection('pgsql_dono')->table('rascunhos_jornada')->first();
    expect($linha->dados_sensiveis)->not->toContain('+5511888888888')
        ->and($linha->dados)->not->toContain('+5511888888888');
});

it('remove rascunhos descartados e expirados', function (): void {
    [$organizacao] = contextoDeJornada();
    test()->putJson('/api/v1/painel/rascunhos/conectar_whatsapp', [
        'etapa' => 'inicio', 'dados' => [], 'versao' => 0,
    ])->assertOk();
    test()->deleteJson('/api/v1/painel/rascunhos/conectar_whatsapp')->assertNoContent();
    expect(DB::connection('pgsql_dono')->table('rascunhos_jornada')->count())->toBe(0);

    test()->putJson('/api/v1/painel/rascunhos/conectar_whatsapp', [
        'etapa' => 'inicio', 'dados' => [], 'versao' => 0,
    ])->assertOk();
    DB::connection('pgsql_dono')->table('rascunhos_jornada')->update(['expira_em' => now()->subDay()]);
    (new ExpirarRascunhosJornada($organizacao))->handle();
    expect(DB::connection('pgsql_dono')->table('rascunhos_jornada')->count())->toBe(0);
});
