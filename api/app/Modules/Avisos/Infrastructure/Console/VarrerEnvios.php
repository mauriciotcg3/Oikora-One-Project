<?php

namespace App\Modules\Avisos\Infrastructure\Console;

use App\Modules\Avisos\Application\Jobs\EnviarAviso;
use App\Modules\Avisos\Domain\EstadoEnvio;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * `envios:varrer`, a cada minuto (OIK-ADR-0008 §6): reenfileira envios `pendente` sem job há mais de
 * 5 min — o caso do processo que caiu entre o commit e o despacho. Roda por organização, com contexto.
 * Job a mais é inofensivo: o envio é travado e o estado conferido antes do provedor.
 */
final class VarrerEnvios extends Command
{
    protected $signature = 'envios:varrer {--minutos=5}';

    protected $description = 'Reenfileira envios pendentes sem job';

    public function __construct(private readonly ConsultaOrganizacoes $organizacoes)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limite = now()->subMinutes(max(1, (int) $this->option('minutos')));
        $total = 0;

        foreach ($this->organizacoes->idsDisponiveis() as $organizacaoId) {
            $total += DB::transaction(function () use ($organizacaoId, $limite): int {
                DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);

                $ids = DB::table('envios')
                    ->where('estado', EstadoEnvio::Pendente->value)
                    ->where('enfileirado_em', '<', $limite)
                    ->orderBy('id')
                    ->limit(500)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();

                if ($ids !== []) {
                    DB::table('envios')->whereIn('id', $ids)->update(['enfileirado_em' => now(), 'updated_at' => now()]);
                }

                foreach ($ids as $id) {
                    EnviarAviso::dispatch($id, $organizacaoId)->afterCommit();
                }

                return count($ids);
            });
        }

        if ($total > 0) {
            Log::warning('envios.reenfileirados', ['quantidade' => $total]);
        }
        $this->info("{$total} envio(s) reenfileirado(s).");

        return self::SUCCESS;
    }
}
