<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class JobQueRegistraNoLog implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Log::info('job.executado');
    }
}

beforeEach(function (): void {
    capturarLog();

    Route::get('/api/v1/_teste/log', function () {
        Log::info('rota.executada');

        return ['ok' => true];
    });
    Route::get('/api/v1/_teste/despacha', function () {
        Log::info('rota.despachou');
        dispatch(new JobQueRegistraNoLog);

        return ['ok' => true];
    });
});

it('gera um UUID quando a requisição chega sem X-Request-Id e o devolve no cabeçalho e no erro', function (): void {
    $resposta = $this->getJson('/api/v1/_teste/nao-existe')->assertNotFound();

    $id = $resposta->headers->get('X-Request-Id');
    expect(Str::isUuid($id))->toBeTrue()
        ->and($resposta->json('request_id'))->toBe($id);
});

it('devolve o X-Request-Id recebido e o grava no log', function (): void {
    $this->withHeader('X-Request-Id', 'borda-0123456789')
        ->getJson('/api/v1/_teste/log')
        ->assertOk()
        ->assertHeader('X-Request-Id', 'borda-0123456789');

    $linha = collect(linhasDoLog())->firstWhere('mensagem', 'rota.executada');
    expect($linha['request_id'])->toBe('borda-0123456789');
});

it('ignora X-Request-Id inseguro e gera outro', function (): void {
    $resposta = $this->withHeader('X-Request-Id', "x\nlinha-injetada")->getJson('/api/v1/_teste/log')->assertOk();

    expect(Str::isUuid($resposta->headers->get('X-Request-Id')))->toBeTrue();
});

it('grava cada linha como JSON com request_id, trace_id, ambiente, module e action', function (): void {
    $this->withHeaders([
        'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        'X-Cliente' => 'painel@1.2.3',
    ])->getJson('/api/v1/_teste/log')->assertOk();

    $linhas = linhasDoLog();
    expect($linhas)->not->toBeEmpty();

    foreach ($linhas as $linha) {
        expect($linha)->toHaveKeys(['request_id', 'trace_id', 'ambiente', 'module', 'action']);
    }

    $linha = collect($linhas)->firstWhere('mensagem', 'rota.executada');
    expect($linha['trace_id'])->toBe('4bf92f3577b34da6a3ce929d0e0e4736')
        ->and($linha['ambiente'])->toBe('testing')
        ->and($linha['cliente'])->toBe('painel@1.2.3');
});

it('faz o job despachado na requisição registrar o mesmo request_id como correlation id', function (): void {
    $resposta = $this->getJson('/api/v1/_teste/despacha')->assertOk();
    $id = $resposta->headers->get('X-Request-Id');

    $job = collect(linhasDoLog())->firstWhere('mensagem', 'job.executado');
    expect($job)->not->toBeNull()
        ->and($job['request_id'])->toBe($id)
        ->and($job['correlation_id'])->toBe($id)
        ->and($job['action'])->toBe('JobQueRegistraNoLog');
});
