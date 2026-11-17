<?php

use App\Modules\Avisos\Application\CanalDeEmail;
use App\Modules\Avisos\Application\CanalDeSms;
use App\Modules\Avisos\Application\CanalDeWhatsApp;
use App\Modules\Avisos\Application\FalhaDefinitivaDeEnvio;
use App\Modules\Avisos\Application\FalhaTemporariaDeEnvio;
use App\Modules\Avisos\Application\MensagemDeCodigo;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppCodigos;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkCodigos;
use App\Modules\Avisos\Infrastructure\Canais\Twilio\TwilioCodigos;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.postmark.token' => 'token-postmark',
        'services.postmark.remetente' => 'nao-responda@oikora.test',
        'services.meta_whatsapp.token' => 'token-meta',
        'services.meta_whatsapp.numero_id' => '1234567890',
        'services.twilio.conta_sid' => 'AC123',
        'services.twilio.token' => 'token-twilio',
        'services.twilio.servico_sid' => 'MG123',
    ]);
});

function codigoPara(string $destino, ?string $template = null): MensagemDeCodigo
{
    return new MensagemDeCodigo(destino: $destino, codigo: '482913', finalidade: 'dois_fatores', template: $template);
}

it('envia o e-mail de código pelo Postmark e devolve o id da mensagem', function (): void {
    Http::fake(['api.postmarkapp.com/*' => Http::response(['ErrorCode' => 0, 'MessageID' => 'pm-1'])]);

    $id = app(PostmarkCodigos::class)->enviar(codigoPara('maria@exemplo.test'));

    expect($id)->toBe('pm-1');
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.postmarkapp.com/email'
        && $r->hasHeader('X-Postmark-Server-Token', 'token-postmark')
        && $r['To'] === 'maria@exemplo.test'
        && $r['MessageStream'] === 'outbound'
        && str_contains($r['TextBody'], '482913'));
});

it('classifica os erros do Postmark', function (int $status, int $erro, string $classe, string $motivo): void {
    Http::fake(['api.postmarkapp.com/*' => Http::response(['ErrorCode' => $erro, 'Message' => 'x'], $status)]);

    expect(fn () => app(PostmarkCodigos::class)->enviar(codigoPara('maria@exemplo.test')))
        ->toThrow($classe, $motivo);
})->with([
    'destinatário inativo' => [422, 406, FalhaDefinitivaDeEnvio::class, 'destinatario_inativo'],
    'e-mail inválido' => [422, 300, FalhaDefinitivaDeEnvio::class, 'email_invalido'],
    'instabilidade' => [503, 0, FalhaTemporariaDeEnvio::class, 'postmark_indisponivel'],
    'limite de taxa' => [429, 0, FalhaTemporariaDeEnvio::class, 'postmark_indisponivel'],
]);

it('envia o código pelo WhatsApp da plataforma com o template authentication', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    $id = app(MetaWhatsAppCodigos::class)->enviar(codigoPara('+5511987654321', 'authentication'));

    expect($id)->toBe('wamid.1');
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://graph.facebook.com/v23.0/1234567890/messages'
        && $r->hasHeader('Authorization', 'Bearer token-meta')
        && $r['to'] === '5511987654321'
        && $r['template']['name'] === 'authentication'
        && $r['template']['language']['code'] === 'pt_BR'
        && $r['template']['components'][0]['parameters'][0]['text'] === '482913'
        && $r['template']['components'][1]['sub_type'] === 'url');
});

it('classifica os erros da Meta', function (int $status, int $erro, string $classe, string $motivo): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => $erro, 'message' => 'x']], $status)]);

    expect(fn () => app(MetaWhatsAppCodigos::class)->enviar(codigoPara('+5511987654321', 'authentication')))
        ->toThrow($classe, $motivo);
})->with([
    'número sem WhatsApp' => [400, 131026, FalhaDefinitivaDeEnvio::class, 'numero_sem_whatsapp'],
    'template rejeitado' => [400, 132001, FalhaDefinitivaDeEnvio::class, 'template_rejeitado'],
    'token inválido' => [401, 190, FalhaDefinitivaDeEnvio::class, 'credencial_invalida'],
    'limite de taxa' => [400, 130429, FalhaTemporariaDeEnvio::class, 'meta_indisponivel'],
    'instabilidade sem código' => [502, 0, FalhaTemporariaDeEnvio::class, 'meta_indisponivel'],
]);

it('envia o SMS de reserva pelo serviço de mensagens do Twilio', function (): void {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

    $id = app(TwilioCodigos::class)->enviar(codigoPara('+5511987654321'));

    expect($id)->toBe('SM1');
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('AC123:token-twilio'))
        && $r['MessagingServiceSid'] === 'MG123'
        && $r['To'] === '+5511987654321'
        && str_contains($r['Body'], '482913'));
});

it('classifica os erros do Twilio e a falta de conexão', function (): void {
    Http::fake(['api.twilio.com/*' => Http::response(['code' => 21211, 'message' => 'x'], 400)]);
    expect(fn () => app(TwilioCodigos::class)->enviar(codigoPara('+5511987654321')))
        ->toThrow(FalhaDefinitivaDeEnvio::class, 'numero_invalido');

    Http::fake(['api.twilio.com/*' => fn () => throw new ConnectionException('timeout')]);
    expect(fn () => app(TwilioCodigos::class)->enviar(codigoPara('+5511987654321')))
        ->toThrow(FalhaTemporariaDeEnvio::class, 'timeout');
});

it('recusa sem chamar a rede quando falta credencial', function (): void {
    config(['services.twilio.token' => null]);
    Http::fake();

    expect(fn () => app(TwilioCodigos::class)->enviar(codigoPara('+5511987654321')))
        ->toThrow(FalhaDefinitivaDeEnvio::class, 'provedor_nao_configurado');
    Http::assertNothingSent();
});

it('não registra destino, código nem token nos logs do provedor', function (): void {
    $logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logs): void {
        $logs[] = json_encode([$e->message, $e->context]);
    });
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131026]], 400)]);

    try {
        app(MetaWhatsAppCodigos::class)->enviar(codigoPara('+5511987654321', 'authentication'));
    } catch (FalhaDefinitivaDeEnvio) {
    }

    $tudo = implode("\n", $logs);
    expect($tudo)->toContain('avisos.provedor.chamada')
        ->and($tudo)->not->toContain('5511987654321')
        ->and($tudo)->not->toContain('482913')
        ->and($tudo)->not->toContain('token-meta');
});

it('usa os provedores reais só quando a configuração manda', function (): void {
    expect(app(CanalDeWhatsApp::class))->toBeInstanceOf(MetaWhatsAppFalso::class);

    config(['oikora.codigos.provedores' => 'real']);

    expect(app(CanalDeEmail::class))->toBeInstanceOf(PostmarkCodigos::class)
        ->and(app(CanalDeWhatsApp::class))->toBeInstanceOf(MetaWhatsAppCodigos::class)
        ->and(app(CanalDeSms::class))->toBeInstanceOf(TwilioCodigos::class);
});

it('com provedores reais, cai do WhatsApp sem conta para o SMS do Twilio', function (): void {
    config(['oikora.codigos.provedores' => 'real']);
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['code' => 131026]], 400),
        'api.twilio.com/*' => Http::response(['sid' => 'SM9'], 201),
    ]);

    $id = emitirCodigo(criarConta(), 'dois_fatores', 'whatsapp');
    $linha = DB::connection('pgsql_dono')->table('codigos_verificacao')->find($id);

    expect($linha->canal)->toBe('sms')
        ->and($linha->envio_estado)->toBe('enviado');
    Http::assertSentCount(2);
});
