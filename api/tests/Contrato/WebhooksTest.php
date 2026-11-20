<?php

use App\Modules\Avisos\Application\Jobs\ProcessarWebhook;
use App\Modules\Avisos\Domain\TipoAviso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'oikora.webhooks.meta.app_secret' => 'meta-secret-de-teste',
        'oikora.webhooks.meta.verify_token' => 'meta-verify-de-teste',
        'oikora.webhooks.postmark.usuario' => 'postmark-teste',
        'oikora.webhooks.postmark.senha' => 'postmark-secret-de-teste',
        'oikora.webhooks.twilio.auth_token' => 'twilio-secret-de-teste',
    ]);
});

function fixtureWebhook(string $arquivo): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/Webhooks/'.$arquivo);
}

/** @return array<string, string> */
function fixtureWebhookJson(string $arquivo): array
{
    return json_decode(fixtureWebhook($arquivo), true, flags: JSON_THROW_ON_ERROR);
}

function assinaturaMeta(string $corpo): string
{
    return 'sha256='.hash_hmac('sha256', $corpo, 'meta-secret-de-teste');
}

/** @param array<string, string> $parametros */
function assinaturaTwilio(string $url, array $parametros): string
{
    ksort($parametros, SORT_STRING);
    $base = $url;
    foreach ($parametros as $nome => $valor) {
        $base .= $nome.$valor;
    }

    return base64_encode(hash_hmac('sha1', $base, 'twilio-secret-de-teste', true));
}

/** @return array{organizacao: int, pessoa: int, contato: int, envio: int} */
function prepararEnvioParaWebhook(string $canal, string $provedor, string $idExterno, string $estado = 'enviado'): array
{
    $organizacao = criarOrganizacao();
    $igreja = criarIgreja($organizacao);
    $pessoa = criarPessoa($organizacao, $igreja);
    $contato = criarContato(
        $organizacao,
        $pessoa,
        $canal === 'email' ? 'email' : 'celular',
        $canal === 'email' ? 'pessoa@exemplo.test' : '+5511999990000',
    );
    if ($canal === 'whatsapp') {
        DB::connection('pgsql_dono')->table('pessoa_contatos')->where('id', $contato)->update(['e_whatsapp' => true]);
    }

    $agora = now();
    $aviso = inserirComoDono('avisos', [
        'organizacao_id' => $organizacao,
        'tipo' => TipoAviso::TransferenciaSolicitada->value,
        'pessoa_id' => $pessoa,
        'destinatario' => 'pessoa:'.$pessoa,
        'referencia_tipo' => 'teste',
        'referencia_id' => 'webhook',
    ]);
    $envio = (int) DB::connection('pgsql_dono')->table('envios')->insertGetId([
        'organizacao_id' => $organizacao,
        'aviso_id' => $aviso,
        'aviso_criado_em' => $agora,
        'canal' => $canal,
        'provedor' => $provedor,
        'id_externo' => $idExterno,
        'estado' => $estado,
        'enviado_em' => $agora,
        'created_at' => $agora,
        'updated_at' => $agora,
    ]);

    return compact('organizacao', 'pessoa', 'contato', 'envio');
}

function executarWebhook(ProcessarWebhook $job): void
{
    app()->call([$job, 'handle']);
}

it('confirma a inscrição da Meta somente com o token configurado', function (): void {
    $this->get('/api/v1/webhooks/meta?hub_mode=subscribe&hub_verify_token=meta-verify-de-teste&hub_challenge=desafio-123')
        ->assertOk()
        ->assertSeeText('desafio-123');

    $this->get('/api/v1/webhooks/meta?hub_mode=subscribe&hub_verify_token=incorreto&hub_challenge=desafio-123')
        ->assertUnauthorized();
});

it('aceita a assinatura válida da Meta, responde rápido e enfileira só dados normalizados', function (): void {
    Queue::fake();
    $corpo = fixtureWebhook('meta-status.json');

    $this->call('POST', '/api/v1/webhooks/meta', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => assinaturaMeta($corpo),
    ], content: $corpo)->assertOk();

    Queue::assertPushedOn('webhooks', ProcessarWebhook::class);
    Queue::assertPushed(ProcessarWebhook::class, fn (ProcessarWebhook $job): bool => ! str_contains(serialize($job), 'conteudo-que-nao-pode-persistir'));
});

it('recusa assinatura inválida da Meta, não enfileira e registra somente contexto seguro', function (): void {
    Queue::fake();
    capturarLog();

    $this->call('POST', '/api/v1/webhooks/meta', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=incorreta',
    ], content: fixtureWebhook('meta-status.json'))->assertUnauthorized();

    Queue::assertNothingPushed();
    expect(conteudoDoLog())->toContain('webhook.assinatura_invalida')
        ->not->toContain('conteudo-que-nao-pode-persistir');
});

it('protege o webhook do Postmark com a credencial HTTP Basic configurada', function (): void {
    Queue::fake();

    $this->withHeader('Authorization', 'Basic '.base64_encode('postmark-teste:postmark-secret-de-teste'))
        ->postJson('/api/v1/webhooks/postmark', fixtureWebhookJson('postmark-delivery.json'))
        ->assertOk();

    Queue::assertPushedOn('webhooks', ProcessarWebhook::class);

    Queue::fake();
    $this->withHeader('Authorization', 'Basic '.base64_encode('postmark-teste:incorreta'))
        ->postJson('/api/v1/webhooks/postmark', fixtureWebhookJson('postmark-delivery.json'))
        ->assertUnauthorized();
    Queue::assertNothingPushed();
});

it('valida a assinatura da Twilio sobre a URL completa e todos os parâmetros', function (): void {
    Queue::fake();
    $parametros = fixtureWebhookJson('twilio-status.json');
    $url = url('/api/v1/webhooks/twilio');

    $this->withHeader('X-Twilio-Signature', assinaturaTwilio($url, $parametros))
        ->post('/api/v1/webhooks/twilio', $parametros)
        ->assertOk();

    Queue::assertPushedOn('webhooks', ProcessarWebhook::class);

    Queue::fake();
    $this->withHeader('X-Twilio-Signature', 'incorreta')
        ->post('/api/v1/webhooks/twilio', $parametros)
        ->assertUnauthorized();
    Queue::assertNothingPushed();
});

it('atualiza a entrega sem permitir que eventos atrasados façam o estado regredir', function (): void {
    $dados = prepararEnvioParaWebhook('whatsapp', 'meta', 'wamid.teste-123');

    executarWebhook(new ProcessarWebhook('meta', 'entregue', 'wamid.teste-123'));
    expect(DB::connection('pgsql_dono')->table('envios')->where('id', $dados['envio'])->value('estado'))->toBe('entregue');

    executarWebhook(new ProcessarWebhook('meta', 'enviado', 'wamid.teste-123'));
    expect(DB::connection('pgsql_dono')->table('envios')->where('id', $dados['envio'])->value('estado'))->toBe('entregue');

    executarWebhook(new ProcessarWebhook('meta', 'falhou_definitivo', 'wamid.teste-123', 'evento_atrasado'));
    expect(DB::connection('pgsql_dono')->table('envios')->where('id', $dados['envio'])->value('estado'))->toBe('entregue');
});

it('ignora com sucesso um envio externo desconhecido', function (): void {
    executarWebhook(new ProcessarWebhook('postmark', 'entregue', 'desconhecido'));

    expect(DB::connection('pgsql_dono')->table('envios')->count())->toBe(0);
});

it('marca falha definitiva no envio e no contato correspondente', function (): void {
    $dados = prepararEnvioParaWebhook('email', 'postmark', 'postmark-bounce-123');

    executarWebhook(new ProcessarWebhook('postmark', 'falhou_definitivo', 'postmark-bounce-123', 'hard_bounce'));

    $envio = DB::connection('pgsql_dono')->table('envios')->where('id', $dados['envio'])->first();
    $contato = DB::connection('pgsql_dono')->table('pessoa_contatos')->where('id', $dados['contato'])->first();
    expect($envio->estado)->toBe('falhou_definitivo')
        ->and($envio->erro_codigo)->toBe('hard_bounce')
        ->and($contato->entrega_falhou_em)->not->toBeNull();
});

it('descadastra contato não obrigatório em reclamação de spam', function (): void {
    $dados = prepararEnvioParaWebhook('email', 'postmark', 'postmark-spam-123');

    executarWebhook(new ProcessarWebhook('postmark', 'reclamacao_spam', 'postmark-spam-123'));

    expect(DB::connection('pgsql_dono')->table('pessoa_contatos')->where('id', $dados['contato'])->value('descadastrado_em'))
        ->not->toBeNull();
});
