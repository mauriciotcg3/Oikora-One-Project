<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Domain\TipoRelacionamento;

/**
 * Relaciona duas pessoas da organização com um tipo fixo e grava a inversa na mesma transação
 * (P FR-012; U-24; CA-18). Quem relaciona precisa editar a ficha de origem; a relacionada pode ser de
 * outra igreja da organização (família espalhada) e é localizada pela busca na denominação.
 */
#[RequerPermissao(Permissao::PessoasEditar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class RelacionarAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private FichaParaEditar $ficha,
        private BuscaDenominacao $busca,
        private RegistrosDaFicha $registros,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid, string $relacionadaUuid, TipoRelacionamento $tipo, ?string $descricao): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }

        $relacionadaId = $this->busca->idPorUuid($relacionadaUuid);
        if ($relacionadaId === null) {
            return Resultado::invalido(['relacionada' => 'pessoa_inexistente']);
        }
        if ($relacionadaId === $pessoa['id']) {
            return Resultado::invalido(['relacionada' => 'relacionamento_consigo']);
        }
        if ($tipo->exigeDescricao() && $descricao === null) {
            return Resultado::invalido(['descricao' => 'descricao_obrigatoria']);
        }
        if (! $tipo->exigeDescricao() && $this->registros->relacionamentoExiste($pessoa['id'], $relacionadaId, $tipo)) {
            return Resultado::falha(422, 'relacionamento_existente', ['mensagem' => 'Esse relacionamento já está registrado.']);
        }

        $ator = $this->alcance->ator();
        $relacionamento = $this->registros->relacionar(
            $ator->organizacaoId,
            $pessoa['id'],
            $relacionadaId,
            $tipo,
            $tipo->exigeDescricao() ? $descricao : null,
            $ator->acessoId,
        );
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaAtualizada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
            campos: ['relacionamentos'],
        );

        return Resultado::ok(['uuid' => $relacionamento['uuid']], 201);
    }
}
