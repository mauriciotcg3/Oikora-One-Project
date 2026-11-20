<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use Illuminate\Support\Facades\DB;

/**
 * Registra a elevação em `eventos_autenticacao` e, quando há organização ativa, na auditoria dela
 * (OIK-ADR-0005 §5; U-31).
 */
final readonly class RegistrarElevacao
{
    public function __construct(
        private EventosAutenticacao $eventos,
        private AcessosDaOrganizacao $acessos,
        private Auditoria $auditoria,
    ) {}

    /** @param 'login'|'dialogo' $via */
    public function executar(int $contaId, ?int $organizacaoId, string $via, ?string $canal, string $ip): void
    {
        $this->eventos->registrar('auth.elevacao', $contaId, Resumo::de((string) $contaId), Resumo::de($ip), $canal);

        if ($organizacaoId === null) {
            return;
        }

        DB::transaction(function () use ($contaId, $organizacaoId, $via, $canal): void {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);

            $this->auditoria->registrar(
                EventoAuditoria::Elevacao,
                $organizacaoId,
                'conta',
                $contaId,
                acessoId: $this->acessos->ativoDaConta($contaId),
                contaId: $contaId,
                metadados: ['via' => $via, 'segundo_fator' => $canal],
            );
        });
    }
}
