<?php

namespace App\Modules\Organizacao\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Application\CadastroDaOrganizacao;
use App\Modules\Organizacao\Application\Governanca;
use App\Modules\Organizacao\Application\Resultado;

/** Cadastra uma igreja local (P FR-001). Igreja não se exclui: não existe rota nem Action para isso. */
#[RequerPermissao(Permissao::IgrejasGerir)]
#[AuditaCom(EventoAuditoria::IgrejaCriada)]
final readonly class CriarIgrejaAction
{
    public function __construct(
        private Governanca $governanca,
        private CadastroDaOrganizacao $cadastro,
    ) {}

    public function executar(string $nome, ?int $regionalId): Resultado
    {
        if ($negada = $this->governanca->negada(Permissao::IgrejasGerir)) {
            return $negada;
        }

        $igreja = $this->cadastro->criarIgreja($this->governanca->organizacaoId(), $nome, $regionalId);
        $this->governanca->auditar(
            EventoAuditoria::IgrejaCriada, 'igreja', $igreja['uuid'], ['nome', 'regional_id'],
            ['regional_id' => $regionalId], $igreja['id'],
        );

        return Resultado::ok(['igreja' => $igreja], 201);
    }
}
