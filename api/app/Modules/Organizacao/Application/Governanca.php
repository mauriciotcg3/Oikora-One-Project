<?php

namespace App\Modules\Organizacao\Application;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\Log;

/**
 * Partes comuns das Actions de Organização: as permissões de governança valem só com escopo organização
 * (a configuração e as igrejas são da organização inteira) e toda mudança é auditada com o ator.
 */
final readonly class Governanca
{
    public function __construct(
        public AlcanceAtual $alcance,
        private Auditoria $auditoria,
    ) {}

    public function pode(Permissao $permissao): bool
    {
        return $this->alcance->temNaOrganizacao($permissao);
    }

    public function negada(Permissao $permissao): ?Resultado
    {
        if ($this->pode($permissao)) {
            return null;
        }

        Log::info('authz.denied', ['permissao' => $permissao->value]);

        return Resultado::falha(403, 'authz.denied');
    }

    public function organizacaoId(): int
    {
        return $this->alcance->ator()->organizacaoId;
    }

    /**
     * @param  list<string>  $campos
     * @param  array<string, bool|int|float|string|null|list<bool|int|float|string|null>>  $metadados
     */
    public function auditar(EventoAuditoria $evento, string $alvoTipo, string $alvoId, array $campos, array $metadados = [], ?int $igrejaId = null): void
    {
        $ator = $this->alcance->ator();

        $this->auditoria->registrar(
            evento: $evento,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: $alvoTipo,
            alvoId: $alvoId,
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $igrejaId,
            campos: $campos,
            metadados: $metadados,
        );
    }
}
