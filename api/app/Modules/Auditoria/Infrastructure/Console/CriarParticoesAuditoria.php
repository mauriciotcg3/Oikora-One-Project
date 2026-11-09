<?php

namespace App\Modules\Auditoria\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CriarParticoesAuditoria extends Command
{
    protected $signature = 'auditoria:criar-particoes {--meses=2}';

    protected $description = 'Cria antecipadamente partições mensais da auditoria';

    public function handle(): int
    {
        $meses = max(1, (int) $this->option('meses'));
        $conexao = DB::connection('pgsql_dono');

        for ($indice = 0; $indice < $meses; $indice++) {
            $inicio = Carbon::now('UTC')->startOfMonth()->addMonths($indice);
            $fim = $inicio->copy()->addMonth();
            $nome = 'auditoria_'.$inicio->format('Ym');
            $limiteInferior = $inicio->format('Y-m-d H:i:sP');
            $limiteSuperior = $fim->format('Y-m-d H:i:sP');
            $conexao->statement(
                "create table if not exists {$nome} partition of auditoria "
                ."for values from ('{$limiteInferior}') to ('{$limiteSuperior}')",
            );
            $conexao->statement("alter table {$nome} enable row level security");
            $conexao->statement("alter table {$nome} force row level security");
            $conexao->statement("drop policy if exists isolamento_organizacao on {$nome}");
            $conexao->statement(
                "create policy isolamento_organizacao on {$nome} "
                .'using (organizacao_id = (select app_organizacao_atual())) '
                .'with check (organizacao_id = (select app_organizacao_atual()))',
            );
            $conexao->statement("revoke update, delete, truncate on {$nome} from oikora_app");
        }

        return self::SUCCESS;
    }
}
