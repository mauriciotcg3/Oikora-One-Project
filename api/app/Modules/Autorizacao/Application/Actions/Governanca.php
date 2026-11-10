<?php

namespace App\Modules\Autorizacao\Application\Actions;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\AlcanceDaRequisicao;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\Log;

/** Partes comuns das Actions de papéis e acessos: exigência de governança e auditoria do ator. */
final readonly class Governanca
{
    public function __construct(
        public AlcanceDaRequisicao $alcance,
        private Auditoria $auditoria,
    ) {}

    /** Permissões de governança valem só com escopo organização. `authz.denied` fica no log técnico. */
    public function negada(Permissao $permissao): ?Resultado
    {
        if ($this->alcance->temNaOrganizacao($permissao)) {
            return null;
        }

        Log::info('authz.denied', ['permissao' => $permissao->value]);

        return Resultado::falha(403, 'authz.denied');
    }

    /** @param  array<string, bool|int|float|string|null|list<bool|int|float|string|null>>  $metadados */
    public function auditar(EventoAuditoria $evento, string $alvoTipo, string $alvoId, array $metadados = []): void
    {
        $ator = $this->alcance->ator();

        $this->auditoria->registrar(
            evento: $evento,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: $alvoTipo,
            alvoId: $alvoId,
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            metadados: $metadados,
        );
    }
}
