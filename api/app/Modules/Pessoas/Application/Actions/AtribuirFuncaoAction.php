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

/**
 * Atribui uma função da lista `funcao` (só itens ativos, conferidos pela borda) com período: `desde`
 * obrigatório e `ate` opcional, nunca anterior a `desde` (P FR-013; CA-18; CA-30).
 */
#[RequerPermissao(Permissao::PessoasEditar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class AtribuirFuncaoAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private FichaParaEditar $ficha,
        private RegistrosDaFicha $registros,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid, int $funcaoId, DateTimeImmutable $desde, ?DateTimeImmutable $ate): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }
        if ($ate !== null && $ate < $desde) {
            return Resultado::invalido(['ate' => 'ate_antes_de_desde']);
        }

        $ator = $this->alcance->ator();
        $funcao = $this->registros->atribuirFuncao($ator->organizacaoId, $pessoa['id'], $funcaoId, $desde, $ate, $ator->acessoId);
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

        return Resultado::ok(['uuid' => $funcao['uuid']], 201);
    }
}
