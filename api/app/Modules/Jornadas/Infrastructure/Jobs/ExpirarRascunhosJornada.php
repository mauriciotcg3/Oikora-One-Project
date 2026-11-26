<?php

namespace App\Modules\Jornadas\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ExpirarRascunhosJornada implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $organizacaoId) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $this->organizacaoId]);
            $removidos = DB::table('rascunhos_jornada')->where('expira_em', '<=', now())->delete();
            Log::info('jornada.rascunhos_expirados', ['organizacao_id' => $this->organizacaoId, 'resultado' => 'sucesso', 'quantidade' => $removidos]);
        });
    }
}
