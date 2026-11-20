<?php

namespace App\Modules\Avisos\Application\Jobs;

use App\Modules\Avisos\Application\EntregarAviso;
use App\Shared\Tenancy\Jobs\ContextoOrganizacaoJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Envia um envio da caixa de saída (OIK-ADR-0008 §2–§4). O payload leva só ids. Entrega "pelo menos uma
 * vez": o envio é travado (`FOR UPDATE SKIP LOCKED`) e o estado conferido antes de chamar o provedor.
 * As novas tentativas são controladas pelo próprio envio (`release` com a espera do ADR), para o estado
 * gravado não se perder no rollback da transação do job.
 */
final class EnviarAviso implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /** Até 5 tentativas: o limite real é `envios.tentativas`; aqui só uma folga para o `release`. */
    public int $tries = 6;

    public function __construct(
        public readonly int $envioId,
        public readonly int $organizacao_id,
    ) {
        $this->onQueue('avisos');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ContextoOrganizacaoJob];
    }

    public function handle(EntregarAviso $entregar): void
    {
        $espera = $entregar->executar($this->envioId);

        if ($espera !== null) {
            $this->release($espera);
        }
    }
}
