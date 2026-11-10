<?php

namespace App\Shared\Observabilidade;

use App\Shared\Observabilidade\Log\Origem;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Correlação em jobs e rotinas (OIK-ADR-0010 §9; COMM-005). O framework reidrata o Context no
 * `JobProcessing` antes deste listener: o job vê o `request_id` de quem o despachou e o usa como
 * `correlation_id`. Rotina agendada começa uma correlação nova.
 */
final class ObservabilidadeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Rastreamento::class);
    }

    public function boot(): void
    {
        // Envia os spans acumulados ao fim da requisição ou do comando, sem segurar a resposta.
        $this->app->terminating(fn () => $this->app->make(Rastreamento::class)->encerrar());

        Event::listen(function (JobProcessing $evento): void {
            Correlacao::garantir();
            [$modulo, $acao] = Origem::daAcao($evento->job->resolveName());
            Context::add('module', $modulo);
            Context::add('action', $acao);
        });

        $encerrar = static function (): void {
            Context::forget(['module', 'action']);
        };
        Event::listen(JobProcessed::class, $encerrar);
        Event::listen(JobExceptionOccurred::class, $encerrar);

        Event::listen(function (ScheduledTaskStarting $evento): void {
            Context::forget(['request_id', 'correlation_id', 'trace_id', 'module', 'action']);
            Correlacao::garantir();
            Context::add('module', 'Agendador');
            Context::add('action', $evento->task->description ?? $evento->task->command);
        });
    }
}
