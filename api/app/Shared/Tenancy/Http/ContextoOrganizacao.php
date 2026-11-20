<?php

namespace App\Shared\Tenancy\Http;

use App\Shared\Tenancy\Contracts\ResolvedorOrganizacaoAtiva;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Instala o contexto RLS durante uma única requisição protegida por organização. */
final readonly class ContextoOrganizacao
{
    public function __construct(private ResolvedorOrganizacaoAtiva $resolvedor) {}

    public function handle(Request $request, Closure $next): Response
    {
        return DB::transaction(function () use ($request, $next): Response {
            $organizacaoId = $this->resolvedor->resolver($request);
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);

            // O front conhece só o identificador público (uuid) da organização ativa, que vem do `/me`.
            $esperada = $request->header('X-Organizacao-Esperada');
            if ($esperada !== null && ! hash_equals($this->uuidDa($organizacaoId), $esperada)) {
                return new JsonResponse(['codigo' => 'organizacao_trocada'], Response::HTTP_CONFLICT);
            }

            // Os middlewares seguintes (ator e alcance) leem daqui, sem resolver de novo.
            $request->attributes->set('organizacao_id', $organizacaoId);

            if ($request->user() !== null) {
                DB::select("select set_config('app.conta_id', ?, true)", [(string) $request->user()->getAuthIdentifier()]);
            }

            return $next($request);
        });
    }

    private function uuidDa(int $organizacaoId): string
    {
        $uuid = DB::table('organizacoes')->where('id', $organizacaoId)->value('uuid');

        return is_string($uuid) ? $uuid : '';
    }
}
