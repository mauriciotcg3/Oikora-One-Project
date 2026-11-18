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
use App\Modules\Pessoas\Application\Consultas\Fichas;
use App\Modules\Pessoas\Domain\Situacao;
use Illuminate\Support\Facades\Log;

/**
 * Inativa ou reativa com motivo da lista própria (RN-06, RN-07; CA-15; U-28) e evento `situacao_alterada`.
 * O vínculo e a matrícula não mudam: a reativação volta ao mesmo vínculo.
 */
#[RequerPermissao(Permissao::PessoasEditar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class AlterarSituacaoAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Fichas $fichas,
        private BuscaDenominacao $busca,
        private VinculosDePessoas $vinculos,
        private Auditoria $auditoria,
    ) {}

    /** @param  int  $motivoId  item ativo da lista da situação de destino, já conferido pela borda */
    public function executar(string $uuid, Situacao $destino, int $motivoId): Resultado
    {
        $pessoa = $this->fichas->localizar($uuid);
        if ($pessoa === null) {
            return $this->busca->existe($uuid) ? $this->negar(Permissao::PessoasVer) : Resultado::falha(404, 'pessoa_inexistente');
        }
        if (! $this->alcance->pode(Permissao::PessoasEditar, $pessoa['igreja_id'])) {
            return $this->negar(Permissao::PessoasEditar);
        }

        $atual = $this->vinculos->estado($pessoa['id'])['situacao'];
        if ($atual === $destino) {
            return Resultado::falha(422, 'situacao_inalterada', [
                'mensagem' => $destino === Situacao::Ativa ? 'A pessoa já está ativa.' : 'A pessoa já está inativa.',
            ]);
        }

        $ator = $this->alcance->ator();
        $this->vinculos->alterarSituacao($pessoa['id'], $destino);
        $this->vinculos->registrarEvento($ator->organizacaoId, $pessoa['id'], 'situacao_alterada', $motivoId, [
            'de' => $atual->value,
            'para' => $destino->value,
        ], $ator->acessoId);
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaAtualizada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
            campos: ['situacao'],
        );

        return Resultado::ok();
    }

    private function negar(Permissao $permissao): Resultado
    {
        Log::info('authz.denied', ['permissao' => $permissao->value]);

        return Resultado::falha(403, 'authz.denied');
    }
}
