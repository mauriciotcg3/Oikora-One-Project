<?php

namespace App\Shared\Tenancy\Jobs;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/** Instala o contexto RLS durante uma única execução de job. */
final class ContextoOrganizacaoJob
{
    public function handle(object $job, Closure $next): void
    {
        if ($job instanceof JobSemOrganizacao) {
            $next($job);

            return;
        }

        $organizacaoId = property_exists($job, 'organizacao_id') ? $job->organizacao_id : null;

        if (! is_int($organizacaoId) || $organizacaoId <= 0 || ! $this->organizacaoDisponivel($organizacaoId)) {
            $this->falhar($job, new RuntimeException('tenant.context.missing'));

            return;
        }

        DB::transaction(function () use ($job, $next, $organizacaoId): void {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
            $next($job);
        });
    }

    private function organizacaoDisponivel(int $organizacaoId): bool
    {
        $consulta = DB::table('organizacoes')->where('id', $organizacaoId);

        // A T-08 cria o estado; até lá, existir é a única condição representável pelo schema.
        if (Schema::hasColumn('organizacoes', 'estado')) {
            $consulta->whereIn('estado', ['ativa', 'em_migracao']);
        }

        return $consulta->exists();
    }

    private function falhar(object $job, Throwable $falha): void
    {
        if (method_exists($job, 'fail')) {
            $job->fail($falha);

            return;
        }

        throw $falha;
    }
}
