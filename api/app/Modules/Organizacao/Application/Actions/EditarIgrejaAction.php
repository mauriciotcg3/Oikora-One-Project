<?php

namespace App\Modules\Organizacao\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Application\CadastroDaOrganizacao;
use App\Modules\Organizacao\Application\Governanca;
use App\Modules\Organizacao\Application\Resultado;

/** Edita nome e regional de uma igreja (P FR-001). A auditoria leva os nomes dos campos, não o nome. */
#[RequerPermissao(Permissao::IgrejasGerir)]
#[AuditaCom(EventoAuditoria::IgrejaAtualizada)]
final readonly class EditarIgrejaAction
{
    public function __construct(
        private Governanca $governanca,
        private CadastroDaOrganizacao $cadastro,
    ) {}

    /** @param  array{nome?: string, regional_id?: int|null}  $dados */
    public function executar(string $uuid, array $dados): Resultado
    {
        if ($negada = $this->governanca->negada(Permissao::IgrejasGerir)) {
            return $negada;
        }

        $igreja = $this->cadastro->igreja($uuid);
        if ($igreja === null) {
            return Resultado::falha(404, 'igreja_inexistente');
        }

        $mudancas = array_filter(
            $dados,
            static fn (mixed $valor, string $campo): bool => $igreja[$campo] !== $valor,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($mudancas !== []) {
            $this->cadastro->atualizarIgreja($igreja['id'], $mudancas);
            $metadados = array_key_exists('regional_id', $mudancas) ? ['regional_id' => $mudancas['regional_id']] : [];
            $this->governanca->auditar(EventoAuditoria::IgrejaAtualizada, 'igreja', $uuid, array_keys($mudancas), $metadados, $igreja['id']);
        }

        return Resultado::ok(['igreja' => $this->cadastro->igreja($uuid)]);
    }
}
