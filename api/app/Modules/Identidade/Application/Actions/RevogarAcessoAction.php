<?php

namespace App\Modules\Identidade\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\ConcessaoDeAcesso;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Identidade\Application\Convites;
use Illuminate\Support\Facades\Log;

/**
 * Revoga o acesso de uma conta à organização (P FR-004; CA-09; OIK-ADR-0003 §11). A organização da
 * sessão é revalidada a cada pedido, então a próxima requisição daquela conta ao painel já é negada.
 * Nunca deixa a organização sem Administrador ativo (OIK-ADR-0004 §3).
 */
#[RequerPermissao(Permissao::AcessosGerir)]
#[AuditaCom(EventoAuditoria::AcessoRevogado)]
final readonly class RevogarAcessoAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Convites $convites,
        private ConcessaoDeAcesso $concessao,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid): Resultado
    {
        if (! $this->alcance->temNaOrganizacao(Permissao::AcessosGerir)) {
            Log::info('authz.denied', ['permissao' => Permissao::AcessosGerir->value]);

            return Resultado::falha(403, 'authz.denied');
        }

        $acesso = $this->convites->acessoPorUuid($uuid);
        if ($acesso === null) {
            return Resultado::falha(404, 'acesso_inexistente');
        }
        if (! $acesso['ativo']) {
            return Resultado::falha(422, 'acesso_revogado');
        }

        $ator = $this->alcance->ator();
        if ($this->concessao->ultimoAdministrador($acesso['id'])) {
            $this->auditoria->registrar(
                evento: EventoAuditoria::UltimoAdministradorBloqueado,
                organizacaoId: $ator->organizacaoId,
                alvoTipo: 'acesso',
                alvoId: $acesso['uuid'],
                acessoId: $ator->acessoId,
                contaId: $ator->contaId,
                metadados: ['motivo_codigo' => 'revogar_acesso'],
            );

            return Resultado::falha(422, 'ultimo_administrador', ['mensagem' => 'A organização precisa de ao menos um Administrador ativo.']);
        }

        $this->convites->revogarAcesso($acesso['id'], $ator->acessoId);
        $this->auditoria->registrar(
            evento: EventoAuditoria::AcessoRevogado,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'acesso',
            alvoId: $acesso['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
        );

        return Resultado::ok(status: 204);
    }
}
