<?php

namespace App\Modules\Autorizacao\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\Papeis;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;

/** Remove uma atribuição; recusa tirar o último Administrador ativo da organização (RN-17). */
#[RequerPermissao(Permissao::AcessosGerir)]
#[AuditaCom(EventoAuditoria::AtribuicaoRevogada)]
final readonly class RemoverAtribuicaoAction
{
    public function __construct(
        private Governanca $governanca,
        private Papeis $papeis,
        private AcessosDaOrganizacao $acessos,
    ) {}

    public function executar(string $acessoUuid, string $atribuicaoUuid): Resultado
    {
        if ($negada = $this->governanca->negada(Permissao::AcessosGerir)) {
            return $negada;
        }

        $acesso = $this->acessos->porUuid($acessoUuid);
        $atribuicao = $acesso === null ? null : $this->papeis->atribuicaoDoAcesso($acesso['id'], $atribuicaoUuid);
        if ($atribuicao === null) {
            return Resultado::falha(404, 'atribuicao_inexistente');
        }

        if ($this->ehUltimoAdministrador($atribuicao)) {
            $this->governanca->auditar(EventoAuditoria::UltimoAdministradorBloqueado, 'atribuicao', $atribuicao['uuid'], [
                'motivo_codigo' => 'ultima_atribuicao',
            ]);

            return Resultado::falha(422, 'ultimo_administrador');
        }

        $permissoes = $this->papeis->porUuid($atribuicao['papel_uuid'])['permissoes'] ?? [];
        $this->papeis->removerAtribuicao($atribuicao['id']);

        $this->governanca->auditar(EventoAuditoria::AtribuicaoRevogada, 'atribuicao', $atribuicao['uuid'], [
            'acesso_alvo_id' => $atribuicao['acesso_id'],
            'papel_id' => $atribuicao['papel_id'],
            'permissoes' => $permissoes,
            'escopo_tipo' => $atribuicao['escopo_tipo'],
            'escopo_id' => $atribuicao['escopo_id'],
        ]);

        return Resultado::ok(status: 204);
    }

    /** @param  array{id: int, sistema: string|null, escopo_tipo: string}  $atribuicao */
    private function ehUltimoAdministrador(array $atribuicao): bool
    {
        if ($atribuicao['sistema'] !== 'administrador' || $atribuicao['escopo_tipo'] !== EscopoTipo::Organizacao->value) {
            return false;
        }

        return $this->acessos->ativos($this->papeis->acessosAdministradores($atribuicao['id'])) === [];
    }
}
