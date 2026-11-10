<?php

namespace App\Modules\Autorizacao\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\AntiEscalada;
use App\Modules\Autorizacao\Application\Papeis;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;

/** Atribui um papel a um acesso num escopo, sem conceder o que o ator não tem (OIK-ADR-0004 §3). */
#[RequerPermissao(Permissao::AcessosGerir)]
#[AuditaCom(EventoAuditoria::AtribuicaoConcedida)]
final readonly class AtribuirPapelAction
{
    public function __construct(
        private Governanca $governanca,
        private Papeis $papeis,
        private AntiEscalada $antiEscalada,
        private AcessosDaOrganizacao $acessos,
        private CatalogoIgrejas $igrejas,
    ) {}

    public function executar(string $acessoUuid, string $papelUuid, EscopoTipo $tipo, ?string $escopoUuid): Resultado
    {
        if ($negada = $this->governanca->negada(Permissao::AcessosGerir)) {
            return $negada;
        }

        $acesso = $this->acessos->porUuid($acessoUuid);
        if ($acesso === null) {
            return Resultado::falha(404, 'acesso_inexistente');
        }
        if (! $acesso['ativo']) {
            return Resultado::falha(422, 'acesso_revogado');
        }

        $papel = $this->papeis->porUuid($papelUuid);
        if ($papel === null) {
            return Resultado::falha(422, 'papel_inexistente');
        }

        $escopoId = match ($tipo) {
            EscopoTipo::Organizacao => null,
            EscopoTipo::Regional => $escopoUuid === null ? null : $this->igrejas->regionalPorUuid($escopoUuid),
            EscopoTipo::Igreja => $escopoUuid === null ? null : $this->igrejas->igrejaPorUuid($escopoUuid),
        };
        if ($tipo !== EscopoTipo::Organizacao && $escopoId === null) {
            return Resultado::falha(422, 'escopo_inexistente');
        }

        if ($this->papeis->atribuicaoExiste($acesso['id'], $papel['id'], $tipo->value, $escopoId)) {
            return Resultado::falha(422, 'atribuicao_existente');
        }

        $faltam = $this->antiEscalada->semCobertura($papel['permissoes'], $tipo, $escopoId);
        if ($faltam !== []) {
            $this->governanca->auditar(EventoAuditoria::EscaladaBloqueada, 'acesso', $acessoUuid, [
                'motivo_codigo' => 'escopo_nao_possuido',
                'permissoes' => $faltam,
                'escopo_tipo' => $tipo->value,
                'escopo_id' => $escopoId,
            ]);

            return Resultado::falha(403, 'escalada_bloqueada');
        }

        $atribuicao = $this->papeis->atribuir($this->governanca->alcance->ator()->organizacaoId, $acesso['id'], $papel['id'], $tipo->value, $escopoId);

        $this->governanca->auditar(EventoAuditoria::AtribuicaoConcedida, 'atribuicao', $atribuicao['uuid'], [
            'acesso_alvo_id' => $acesso['id'],
            'papel_id' => $papel['id'],
            'permissoes' => $papel['permissoes'],
            'escopo_tipo' => $tipo->value,
            'escopo_id' => $escopoId,
        ]);

        return Resultado::ok(['atribuicao' => [
            'uuid' => $atribuicao['uuid'],
            'papel' => $papel['uuid'],
            'escopo_tipo' => $tipo->value,
            'escopo' => $tipo === EscopoTipo::Organizacao ? null : $escopoUuid,
        ]], 201);
    }
}
