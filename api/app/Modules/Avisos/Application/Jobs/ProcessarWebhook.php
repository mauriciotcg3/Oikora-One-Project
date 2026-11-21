<?php

namespace App\Modules\Avisos\Application\Jobs;

use App\Modules\Avisos\Application\ProcessarEventoWebhook;
use App\Shared\Tenancy\Jobs\ContextoOrganizacaoJob;
use App\Shared\Tenancy\Jobs\JobSemOrganizacao;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/** O tenant é descoberto por id externo opaco; nenhum payload ou dado pessoal entra na fila. */
final class ProcessarWebhook implements JobSemOrganizacao, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 1800, 7200];

    public function __construct(
        public readonly string $provedor,
        public readonly string $tipo,
        public readonly string $idExterno,
        public readonly ?string $erroCodigo = null,
    ) {
        $this->onQueue('webhooks');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ContextoOrganizacaoJob];
    }

    public function handle(ProcessarEventoWebhook $processar): void
    {
        $processar->executar($this->provedor, $this->tipo, $this->idExterno, $this->erroCodigo);
    }
}
