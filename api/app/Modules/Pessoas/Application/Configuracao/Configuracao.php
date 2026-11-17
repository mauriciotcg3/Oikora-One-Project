<?php

namespace App\Modules\Pessoas\Application\Configuracao;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\Log;

/**
 * Partes comuns das Actions de configuração de Pessoas: `pessoas.configurar` vale só com escopo
 * organização (a configuração é da organização inteira) e toda mudança é auditada com o ator.
 */
final readonly class Configuracao
{
    public function __construct(
        public AlcanceAtual $alcance,
        private Auditoria $auditoria,
    ) {}

    public function negada(): ?Resultado
    {
        if ($this->alcance->temNaOrganizacao(Permissao::PessoasConfigurar)) {
            return null;
        }

        Log::info('authz.denied', ['permissao' => Permissao::PessoasConfigurar->value]);

        return Resultado::falha(403, 'authz.denied');
    }

    public function organizacaoId(): int
    {
        return $this->alcance->ator()->organizacaoId;
    }

    /** @param  array<string, bool|int|float|string|null|list<bool|int|float|string|null>>  $metadados */
    public function auditar(EventoAuditoria $evento, string $alvoTipo, string $alvoId, array $metadados): void
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
