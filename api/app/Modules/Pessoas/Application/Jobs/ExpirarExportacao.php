<?php

namespace App\Modules\Pessoas\Application\Jobs;

use App\Modules\Pessoas\Application\Exportacoes\ArquivosDeExportacao;
use App\Shared\Tenancy\Jobs\ContextoOrganizacaoJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class ExpirarExportacao implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $organizacao_id, public readonly string $exportacaoUuid)
    {
        $this->onQueue('pesadas');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ContextoOrganizacaoJob];
    }

    public function handle(ArquivosDeExportacao $arquivos): void
    {
        $exportacao = DB::table('exportacoes')->where('uuid', $this->exportacaoUuid)->lockForUpdate()->first();
        if ($exportacao === null || $exportacao->estado === 'expirada' || now()->lt($exportacao->expira_em)) {
            return;
        }
        if (is_string($exportacao->arquivo_path) && $exportacao->arquivo_path !== '') {
            $arquivos->apagar($exportacao->arquivo_path);
        }
        DB::table('exportacoes')->where('id', $exportacao->id)->update(['estado' => 'expirada', 'arquivo_path' => null, 'updated_at' => now()]);
    }
}
