<?php

namespace App\Modules\Avisos\Application\Jobs;

use App\Modules\Avisos\Application\EntregarCodigo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Job de identidade, da lista fechada sem organização (OIK-ADR-0008 §5). O payload leva só o id:
 * código e destino são lidos na hora, pelo Contract de Identidade.
 */
final class EnviarCodigo implements ShouldQueue
{
    use Queueable;

    /** O código vale 10 min: duas tentativas em 2 min, depois o canal de reserva (OIK-ADR-0008 §3). */
    public int $tries = 2;

    public function __construct(public readonly int $codigoId)
    {
        $this->onQueue('codigos');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60];
    }

    public function handle(EntregarCodigo $entregar): void
    {
        $entregar->executar($this->codigoId);
    }

    public function failed(Throwable $erro): void
    {
        app(EntregarCodigo::class)->depoisDeEsgotar($this->codigoId, $erro);
    }
}
