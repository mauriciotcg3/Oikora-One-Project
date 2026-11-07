<?php

use App\Modules\Avisos\Application\FalhaDefinitivaDeEnvio;
use App\Modules\Avisos\Application\FalhaTemporariaDeEnvio;
use App\Modules\Avisos\Application\Jobs\EnviarCodigo;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use App\Modules\Avisos\Infrastructure\Canais\Twilio\TwilioFalso;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function linhaDoCodigo(int $id): object
{
    return DB::connection('pgsql_dono')->table('codigos_verificacao')->find($id);
}

/** Roda o job como o worker faria, sem o `sync` da suíte engolir a exceção da tentativa. */
function rodarJob(EnviarCodigo $job): void
{
    app()->call([$job, 'handle']);
}

function jobNaFila(): EnviarCodigo
{
    return Queue::pushedJobs()[EnviarCodigo::class][0]['job'];
}

it('manda o código por e-mail pela fila codigos', function (): void {
    Queue::fake();
    $id = emitirCodigo(criarConta(), 'dois_fatores', 'email');

    Queue::assertPushedOn('codigos', EnviarCodigo::class);
    rodarJob(jobNaFila());

    $mensagem = app(PostmarkFalso::class)->enviados[0];

    expect($mensagem->destino)->toBe('maria@exemplo.test')
        ->and($mensagem->codigo)->toMatch('/^\d{6}$/')
        ->and(linhaDoCodigo($id)->envio_estado)->toBe('enviado');
});

it('manda o código pelo WhatsApp da plataforma com o template authentication', function (): void {
    emitirCodigo(criarConta(), 'dois_fatores', 'whatsapp');

    $mensagem = app(MetaWhatsAppFalso::class)->enviados[0];

    expect($mensagem->destino)->toBe('+5511987654321')
        ->and($mensagem->template)->toBe('authentication');
});

it('cai para o SMS quando o WhatsApp falha de vez', function (): void {
    app(MetaWhatsAppFalso::class)->falharCom(new FalhaDefinitivaDeEnvio('numero_sem_whatsapp'));

    $id = emitirCodigo(criarConta(), 'dois_fatores', 'whatsapp');

    expect(app(TwilioFalso::class)->enviados)->toHaveCount(1)
        ->and(app(TwilioFalso::class)->enviados[0]->destino)->toBe('+5511987654321')
        ->and(linhaDoCodigo($id)->canal)->toBe('sms')
        ->and(linhaDoCodigo($id)->envio_estado)->toBe('enviado');
});

it('não manda SMS para número fora de +55', function (): void {
    app(MetaWhatsAppFalso::class)->falharCom(new FalhaDefinitivaDeEnvio('numero_sem_whatsapp'));

    $id = emitirCodigo(criarConta(['celular' => '+14155550123']), 'dois_fatores', 'whatsapp');

    expect(app(TwilioFalso::class)->enviados)->toBeEmpty()
        ->and(linhaDoCodigo($id)->envio_estado)->toBe('falhou_definitivo')
        ->and(linhaDoCodigo($id)->envio_erro)->toBe('numero_sem_whatsapp');
});

it('limita o SMS a 3 por hora por número', function (): void {
    app(MetaWhatsAppFalso::class)->falharCom(new FalhaDefinitivaDeEnvio('numero_sem_whatsapp'));
    $conta = criarConta();

    foreach (range(1, 3) as $_) {
        emitirCodigo($conta, 'dois_fatores', 'whatsapp');
    }
    $recusado = emitirCodigo($conta, 'dois_fatores', 'whatsapp');

    expect(app(TwilioFalso::class)->enviados)->toHaveCount(3)
        ->and(linhaDoCodigo($recusado)->envio_estado)->toBe('falhou_definitivo')
        ->and(linhaDoCodigo($recusado)->envio_erro)->toBe('limite_sms');

    $this->travel(61)->minutes();
    emitirCodigo($conta, 'dois_fatores', 'whatsapp');

    expect(app(TwilioFalso::class)->enviados)->toHaveCount(4);
});

it('limita o SMS a 10 por dia por número', function (): void {
    app(MetaWhatsAppFalso::class)->falharCom(new FalhaDefinitivaDeEnvio('numero_sem_whatsapp'));
    $conta = criarConta();

    // Duas por hora ficam abaixo do limite horário: quem barra a 11ª é o limite do dia.
    foreach (range(1, 10) as $envio) {
        emitirCodigo($conta, 'dois_fatores', 'whatsapp');
        if ($envio % 2 === 0) {
            $this->travel(61)->minutes();
        }
    }
    $recusado = emitirCodigo($conta, 'dois_fatores', 'whatsapp');

    expect(app(TwilioFalso::class)->enviados)->toHaveCount(10)
        ->and(linhaDoCodigo($recusado)->envio_erro)->toBe('limite_sms');
});

it('tenta de novo em erro temporário: até 2 vezes em 2 minutos', function (): void {
    Queue::fake();
    emitirCodigo(criarConta(), 'dois_fatores', 'email');
    $job = jobNaFila();

    expect($job->tries)->toBe(2)
        ->and(array_sum((array) $job->backoff()))->toBeLessThanOrEqual(120);

    app(PostmarkFalso::class)->falharCom(new FalhaTemporariaDeEnvio('timeout'));

    expect(fn () => rodarJob($job))->toThrow(FalhaTemporariaDeEnvio::class);
});

it('reenvia o mesmo código na segunda tentativa', function (): void {
    Queue::fake();
    $id = emitirCodigo(criarConta(), 'dois_fatores', 'email');
    $job = jobNaFila();
    $hash = linhaDoCodigo($id)->codigo_hash;

    app(PostmarkFalso::class)->falharCom(new FalhaTemporariaDeEnvio('timeout'));
    expect(fn () => rodarJob($job))->toThrow(FalhaTemporariaDeEnvio::class);

    app(PostmarkFalso::class)->voltarAoNormal();
    rodarJob($job);

    expect(linhaDoCodigo($id)->codigo_hash)->toBe($hash)
        ->and(linhaDoCodigo($id)->envio_estado)->toBe('enviado');
});

it('esgotadas as tentativas do WhatsApp, usa o SMS de reserva', function (): void {
    Queue::fake();
    $id = emitirCodigo(criarConta(), 'dois_fatores', 'whatsapp');

    jobNaFila()->failed(new FalhaTemporariaDeEnvio('timeout'));

    expect(app(TwilioFalso::class)->enviados)->toHaveCount(1)
        ->and(linhaDoCodigo($id)->canal)->toBe('sms');
});

it('marca falhou_definitivo quando o e-mail esgota as tentativas', function (): void {
    Queue::fake();
    $id = emitirCodigo(criarConta(), 'dois_fatores', 'email');

    jobNaFila()->failed(new FalhaTemporariaDeEnvio('timeout'));

    expect(linhaDoCodigo($id)->envio_estado)->toBe('falhou_definitivo')
        ->and(linhaDoCodigo($id)->envio_erro)->toBe('timeout');
});

it('não põe código nem destino em claro no payload, nos logs ou no banco depois da entrega', function (): void {
    $logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $linha) use (&$logs): void {
        $logs[] = $linha->message.json_encode($linha->context);
    });

    Queue::fake();
    $id = emitirCodigo(criarConta(), 'dois_fatores', 'email');
    $job = jobNaFila();
    $payload = serialize($job);
    rodarJob($job);

    $codigo = app(PostmarkFalso::class)->enviados[0]->codigo;
    $linha = linhaDoCodigo($id);

    expect($payload)->not->toContain($codigo)->not->toContain('maria@exemplo.test')
        ->and(implode("\n", $logs))->not->toContain($codigo)->not->toContain('maria@exemplo.test')
        ->and($linha->codigo_hash)->not->toContain($codigo)
        ->and($linha->codigo_cifrado)->toBeNull();
});
