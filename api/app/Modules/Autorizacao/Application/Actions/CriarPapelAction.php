<?php

namespace App\Modules\Autorizacao\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\AntiEscalada;
use App\Modules\Autorizacao\Application\Papeis;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;

/** Cria um papel combinando permissões do catálogo (RN-17; CA-11). */
#[RequerPermissao(Permissao::PapeisGerir)]
#[AuditaCom(EventoAuditoria::PapelCriado)]
final readonly class CriarPapelAction
{
    public function __construct(
        private Governanca $governanca,
        private Papeis $papeis,
        private AntiEscalada $antiEscalada,
    ) {}

    /** @param  list<string>  $permissoes  valores do enum Permissao, já validados */
    public function executar(string $nome, array $permissoes): Resultado
    {
        if ($negada = $this->governanca->negada(Permissao::PapeisGerir)) {
            return $negada;
        }

        if ($this->papeis->nomeEmUso($nome)) {
            return Resultado::falha(422, 'nome_em_uso');
        }

        $faltam = $this->antiEscalada->permissoesQueFaltam($permissoes);
        if ($faltam !== []) {
            $this->governanca->auditar(EventoAuditoria::EscaladaBloqueada, 'papel', 'novo', [
                'motivo_codigo' => 'permissao_nao_possuida',
                'permissoes' => $faltam,
            ]);

            return Resultado::falha(403, 'escalada_bloqueada');
        }

        $papel = $this->papeis->criar($this->governanca->alcance->ator()->organizacaoId, $nome, $permissoes);
        $this->governanca->auditar(EventoAuditoria::PapelCriado, 'papel', $papel['uuid'], [
            'permissoes_depois' => $papel['permissoes'],
        ]);

        return Resultado::ok(['papel' => $papel], 201);
    }
}
