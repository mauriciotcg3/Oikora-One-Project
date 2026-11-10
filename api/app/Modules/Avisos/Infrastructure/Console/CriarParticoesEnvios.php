<?php

namespace App\Modules\Avisos\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Cria antes do mês as partições de `envios`, com a mesma RLS da tabela mãe (mesmo padrão da auditoria). */
final class CriarParticoesEnvios extends Command
{
    protected $signature = 'avisos:criar-particoes {--meses=2}';

    protected $description = 'Cria antecipadamente partições mensais de envios';

    public function handle(): int
    {
        $conexao = DB::connection('pgsql_dono');

        for ($indice = 0; $indice < max(1, (int) $this->option('meses')); $indice++) {
            foreach (self::comandosDaParticao(Carbon::now('UTC')->startOfMonth()->addMonths($indice)) as $comando) {
                $conexao->statement($comando);
            }
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    public static function comandosDaParticao(Carbon $inicio): array
    {
        $nome = 'envios_'.$inicio->format('Ym');
        $de = $inicio->format('Y-m-d H:i:sP');
        $ate = $inicio->copy()->addMonth()->format('Y-m-d H:i:sP');

        return [
            "create table if not exists {$nome} partition of envios for values from ('{$de}') to ('{$ate}')",
            "alter table {$nome} enable row level security",
            "alter table {$nome} force row level security",
            "drop policy if exists isolamento_organizacao on {$nome}",
            "create policy isolamento_organizacao on {$nome} "
                .'using (organizacao_id = (select app_organizacao_atual())) '
                .'with check (organizacao_id = (select app_organizacao_atual()))',
        ];
    }
}
