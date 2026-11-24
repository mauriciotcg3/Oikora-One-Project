<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;

/**
 * "Não são a mesma pessoa" (RN-23; plan.md §7): o par sai da lista de possíveis duplicados. Quem decide
 * é quem poderia mesclar as duas.
 */
#[RequerPermissao(Permissao::PessoasMesclar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class MarcarNaoDuplicadasAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private FichaParaEditar $ficha,
        private CadastroDePessoas $cadastro,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $pessoaUuid, string $outraUuid): Resultado
    {
        if ($pessoaUuid === $outraUuid) {
            return Resultado::invalido(['outra' => 'mesma_pessoa']);
        }

        $pessoa = $this->ficha->localizar($pessoaUuid, Permissao::PessoasMesclar);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }
        $outra = $this->ficha->localizar($outraUuid, Permissao::PessoasMesclar);
        if ($outra instanceof Resultado) {
            return $outra;
        }

        $ator = $this->alcance->ator();
        $this->cadastro->marcarNaoDuplicadas($ator->organizacaoId, $pessoa['id'], $outra['id'], $ator->acessoId);
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaAtualizada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
            campos: ['nao_duplicada'],
        );

        return Resultado::ok([], 204);
    }
}
