<?php

namespace App\Modules\Identidade\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Identidade\Application\Convites;
use Illuminate\Support\Facades\Log;

/** Revoga um convite ainda pendente: o link deixa de valer na hora (RN-12; OIK-ADR-0003 §11). */
#[RequerPermissao(Permissao::AcessosGerir)]
#[AuditaCom(EventoAuditoria::ConviteRevogado)]
final readonly class RevogarConviteAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Convites $convites,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid): Resultado
    {
        if (! $this->alcance->temNaOrganizacao(Permissao::AcessosGerir)) {
            Log::info('authz.denied', ['permissao' => Permissao::AcessosGerir->value]);

            return Resultado::falha(403, 'authz.denied');
        }

        $convite = $this->convites->porUuid($uuid);
        if ($convite === null) {
            return Resultado::falha(404, 'convite_inexistente');
        }
        if ($convite['estado'] !== 'enviado') {
            return Resultado::falha(422, 'convite_nao_pendente', ['mensagem' => 'Este convite já foi aceito, revogado ou expirou.']);
        }

        $ator = $this->alcance->ator();
        $this->convites->revogar($convite['id'], $ator->acessoId);
        $this->auditoria->registrar(
            evento: EventoAuditoria::ConviteRevogado,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'convite',
            alvoId: $convite['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            metadados: [
                'convite_id' => $convite['uuid'],
                'papel_id' => $convite['papel_id'],
                'escopo_tipo' => $convite['escopo_tipo'],
                'escopo_id' => $convite['escopo_id'],
            ],
        );

        return Resultado::ok(status: 204);
    }
}
