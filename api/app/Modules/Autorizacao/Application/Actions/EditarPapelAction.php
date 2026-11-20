<?php

namespace App\Modules\Autorizacao\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\AntiEscalada;
use App\Modules\Autorizacao\Application\Papeis;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;

/**
 * Edita nome e permissões de um papel, inclusive dos prontos (RN-17). O Administrador nunca perde
 * `papeis.gerir` nem `acessos.gerir`; permissão acrescentada passa pela anti-escalada.
 */
#[RequerPermissao(Permissao::PapeisGerir)]
#[AuditaCom(EventoAuditoria::PapelAtualizado)]
final readonly class EditarPapelAction
{
    private const GOVERNANCA_DO_ADMINISTRADOR = [Permissao::PapeisGerir, Permissao::AcessosGerir];

    public function __construct(
        private Governanca $governanca,
        private Papeis $papeis,
        private AntiEscalada $antiEscalada,
    ) {}

    /** @param  list<string>  $permissoes */
    public function executar(string $uuid, ?string $nome, array $permissoes): Resultado
    {
        if ($negada = $this->governanca->negada(Permissao::PapeisGerir)) {
            return $negada;
        }

        $papel = $this->papeis->porUuid($uuid);
        if ($papel === null) {
            return Resultado::falha(404, 'papel_inexistente');
        }

        if ($papel['sistema'] === 'administrador' && ! $this->mantemGovernanca($permissoes)) {
            $this->governanca->auditar(EventoAuditoria::UltimoAdministradorBloqueado, 'papel', $papel['uuid'], [
                'motivo_codigo' => 'governanca_do_administrador',
            ]);

            return Resultado::falha(422, 'administrador_sem_governanca');
        }

        $nomeNovo = $nome ?? $papel['nome'];
        if ($this->papeis->nomeEmUso($nomeNovo, $papel['id'])) {
            return Resultado::falha(422, 'nome_em_uso');
        }

        $faltam = $this->antiEscalada->permissoesQueFaltam(array_values(array_diff($permissoes, $papel['permissoes'])));
        if ($faltam !== []) {
            $this->governanca->auditar(EventoAuditoria::EscaladaBloqueada, 'papel', $papel['uuid'], [
                'motivo_codigo' => 'permissao_nao_possuida',
                'permissoes' => $faltam,
            ]);

            return Resultado::falha(403, 'escalada_bloqueada');
        }

        $organizacaoId = $this->governanca->alcance->ator()->organizacaoId;
        $this->papeis->atualizar($organizacaoId, $papel['id'], $nomeNovo, $permissoes);
        $depois = $this->papeis->porUuid($uuid) ?? $papel;

        $this->governanca->auditar(EventoAuditoria::PapelAtualizado, 'papel', $papel['uuid'], [
            'permissoes_antes' => $papel['permissoes'],
            'permissoes_depois' => $depois['permissoes'],
            'nome_alterado' => $nomeNovo !== $papel['nome'],
        ]);

        return Resultado::ok(['papel' => $depois]);
    }

    /** @param  list<string>  $permissoes */
    private function mantemGovernanca(array $permissoes): bool
    {
        foreach (self::GOVERNANCA_DO_ADMINISTRADOR as $exigida) {
            if (! in_array($exigida->value, $permissoes, true)) {
                return false;
            }
        }

        return true;
    }
}
