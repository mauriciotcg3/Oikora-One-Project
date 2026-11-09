<?php

namespace App\Modules\Auditoria\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AplicarRetencaoAuditoria extends Command
{
    protected $signature = 'auditoria:aplicar-retencao {--anos=5}';

    protected $description = 'Remove partições mensais de auditoria fora da retenção';

    public function handle(): int
    {
        $limite = Carbon::now('UTC')->startOfMonth()->subYears(max(1, (int) $this->option('anos')));
        $conexao = DB::connection('pgsql_dono');
        $particoes = $conexao->select(<<<'SQL'
            select child.relname as nome
            from pg_inherits
            join pg_class parent on parent.oid = inhparent
            join pg_class child on child.oid = inhrelid
            where parent.relname = 'auditoria' and child.relname ~ '^auditoria_[0-9]{6}$'
        SQL);

        foreach ($particoes as $particao) {
            $mes = Carbon::createFromFormat('Ym', substr((string) $particao->nome, -6), 'UTC');
            if ($mes === null) {
                throw new RuntimeException('auditoria.particao_nome_invalido');
            }
            $mes->startOfMonth();
            if ($mes->lt($limite)) {
                $conexao->statement('drop table if exists "'.$particao->nome.'"');
            }
        }

        return self::SUCCESS;
    }
}
