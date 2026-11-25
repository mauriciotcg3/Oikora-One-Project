<?php

namespace App\Modules\Pessoas\Application\Jobs;

use App\Modules\Auditoria\Contracts\OperacoesDesfaziveis;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Pessoas\Application\Mesclagem\Mesclagens;
use App\Shared\Tenancy\Jobs\ContextoOrganizacaoJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ExpirarDesfazerMesclagem implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $organizacao_id)
    {
        $this->onQueue('default');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ContextoOrganizacaoJob];
    }

    public function handle(AlcanceAtual $alcance, OperacoesDesfaziveis $operacoes, Mesclagens $mesclagens): void
    {
        $alcance->comoSistema($this->organizacao_id, function () use ($operacoes, $mesclagens): void {
            $expiradas = DB::transaction(function () use ($operacoes, $mesclagens): int {
                $total = 0;
                foreach ($operacoes->vencidas('mesclagem') as $operacao) {
                    $mesclagens->apagarAbsorvida((int) $operacao['afetados']['absorvida_id']);
                    $operacoes->encerrar($operacao['id'], 'expirada');
                    $total++;
                }

                return $total;
            });
            Log::info('pessoas.mesclagens.expiradas', ['organizacao_id' => $this->organizacao_id, 'contagem' => $expiradas]);
        });
    }
}
