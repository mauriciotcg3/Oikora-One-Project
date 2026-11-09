<?php

namespace App\Shared\Tenancy\Jobs;

use App\Shared\Tenancy\Contracts\OrganizacaoDisponivel;
use Closure;
use Illuminate\Support\Facades\DB;
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

        if (! is_int($organizacaoId) || $organizacaoId <= 0 || ! app(OrganizacaoDisponivel::class)->verificar($organizacaoId)) {
            $this->falhar($job, new RuntimeException('tenant.context.missing'));

            return;
        }

        DB::transaction(function () use ($job, $next, $organizacaoId): void {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
            $next($job);
        });
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
