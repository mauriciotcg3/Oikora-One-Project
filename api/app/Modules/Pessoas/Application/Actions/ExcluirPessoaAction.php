<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Lixeira\Lixeira;

/**
 * Excluir para a lixeira (OIK-ADR-0005 §7; RN-19; CA-07): a ficha some de lista, busca, exportação e
 * duplicados, e pode ser recuperada por 30 dias. O motivo é texto livre: fica só na ficha.
 */
#[RequerPermissao(Permissao::PessoasExcluir)]
#[AuditaCom(EventoAuditoria::PessoaExcluida)]
final readonly class ExcluirPessoaAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private FichaParaEditar $ficha,
        private Lixeira $lixeira,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid, string $motivo): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid, Permissao::PessoasExcluir);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }
        $atual = $this->lixeira->localizar($uuid, true);
        if ($atual === null) {
            return Resultado::falha(404, 'pessoa_inexistente');
        }
        if ($atual['excluida_em'] !== null) {
            return Resultado::falha(422, 'pessoa_na_lixeira');
        }

        $ator = $this->alcance->ator();
        $this->lixeira->excluir($pessoa['id'], $motivo, $ator->acessoId);
        $this->lixeira->evento($ator->organizacaoId, $pessoa['id'], 'excluida', $ator->acessoId);
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaExcluida,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
        );

        return Resultado::ok([], 204);
    }
}
