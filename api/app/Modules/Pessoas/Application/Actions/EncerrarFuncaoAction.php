<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use DateTimeImmutable;

/** Encerra (ou corrige o fim de) uma função da pessoa; `ate` nunca anterior a `desde` (P FR-013). */
#[RequerPermissao(Permissao::PessoasEditar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class EncerrarFuncaoAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private FichaParaEditar $ficha,
        private RegistrosDaFicha $registros,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid, string $funcaoUuid, DateTimeImmutable $ate): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }
        $funcao = $this->registros->funcao($pessoa['id'], $funcaoUuid);
        if ($funcao === null) {
            return Resultado::falha(404, 'funcao_inexistente');
        }
        if ($ate->format('Y-m-d') < $funcao['desde']) {
            return Resultado::invalido(['ate' => 'ate_antes_de_desde']);
        }

        $this->registros->encerrarFuncao($funcao['id'], $ate);
        $ator = $this->alcance->ator();
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaAtualizada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
            campos: ['funcoes'],
        );

        return Resultado::ok();
    }
}
