<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\Actions\Governanca;
use App\Modules\Autorizacao\Contracts\ConcessaoDeAcesso;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;

/** As mesmas regras da atribuição de papel (T-15), aplicadas ao convite (T-22). */
final readonly class ConcessaoDeAcessoPorPapel implements ConcessaoDeAcesso
{
    public function __construct(
        private Papeis $papeis,
        private AntiEscalada $antiEscalada,
        private CatalogoIgrejas $igrejas,
        private Governanca $governanca,
        private AcessosDaOrganizacao $acessos,
    ) {}

    public function conferir(string $papelUuid, EscopoTipo $tipo, ?string $escopoUuid): array|string
    {
        $papel = $this->papeis->porUuid($papelUuid);
        if ($papel === null) {
            return 'papel_inexistente';
        }

        $escopoId = match ($tipo) {
            EscopoTipo::Organizacao => null,
            EscopoTipo::Regional => $escopoUuid === null ? null : $this->igrejas->regionalPorUuid($escopoUuid),
            EscopoTipo::Igreja => $escopoUuid === null ? null : $this->igrejas->igrejaPorUuid($escopoUuid),
        };
        if ($tipo !== EscopoTipo::Organizacao && $escopoId === null) {
            return 'escopo_inexistente';
        }

        $faltam = $this->antiEscalada->semCobertura($papel['permissoes'], $tipo, $escopoId);
        if ($faltam !== []) {
            $this->governanca->auditar(EventoAuditoria::EscaladaBloqueada, 'papel', $papel['uuid'], [
                'motivo_codigo' => 'escopo_nao_possuido',
                'permissoes' => $faltam,
                'escopo_tipo' => $tipo->value,
                'escopo_id' => $escopoId,
            ]);

            return 'escalada_bloqueada';
        }

        return ['papel_id' => $papel['id'], 'permissoes' => $papel['permissoes'], 'escopo_tipo' => $tipo->value, 'escopo_id' => $escopoId];
    }

    public function atribuir(int $organizacaoId, int $acessoId, int $papelId, EscopoTipo $tipo, ?int $escopoId): string
    {
        return $this->papeis->atribuir($organizacaoId, $acessoId, $papelId, $tipo->value, $escopoId)['uuid'];
    }

    public function ultimoAdministrador(int $acessoId): bool
    {
        $administradores = $this->acessos->ativos($this->papeis->acessosAdministradores());

        return $administradores === [$acessoId];
    }

    public function nomeDoPapel(int $papelId): ?string
    {
        foreach ($this->papeis->listar() as $papel) {
            if ($papel['id'] === $papelId) {
                return $papel['nome'];
            }
        }

        return null;
    }
}
