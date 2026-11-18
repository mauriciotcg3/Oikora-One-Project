<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Consultas\Fichas;
use App\Modules\Pessoas\Domain\Situacao;
use App\Modules\Pessoas\Domain\TransicaoVinculoInvalida;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Support\Facades\Log;

/**
 * Muda o vínculo um passo à frente (RN-07; CA-06), com motivo da lista `motivo_vinculo` (RN-06; CA-15),
 * evento `vinculo_alterado` na linha do tempo e auditoria só com nomes de campo. Passar para Membro no
 * modo manual exige matrícula livre na organização, inclusive na lixeira (RN-05; CA-16); o modo
 * automático é recusado enquanto o formato estiver em aberto (U-07). Mudar o vínculo desliga
 * `novo_convertido` (RN-18).
 */
#[RequerPermissao(Permissao::PessoasEditar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class AlterarVinculoAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Fichas $fichas,
        private BuscaDenominacao $busca,
        private VinculosDePessoas $vinculos,
        private ConsultaOrganizacoes $organizacoes,
        private Auditoria $auditoria,
    ) {}

    /** @param  int  $motivoId  item ativo da lista `motivo_vinculo`, já conferido pela borda */
    public function executar(string $uuid, Vinculo $destino, int $motivoId, ?string $matricula): Resultado
    {
        $pessoa = $this->fichas->localizar($uuid);
        if ($pessoa === null) {
            return $this->busca->existe($uuid) ? $this->negar(Permissao::PessoasVer) : Resultado::falha(404, 'pessoa_inexistente');
        }
        if (! $this->alcance->pode(Permissao::PessoasEditar, $pessoa['igreja_id'])) {
            return $this->negar(Permissao::PessoasEditar);
        }

        $estado = $this->vinculos->estado($pessoa['id']);
        if ($estado['situacao'] === Situacao::Inativa) {
            return Resultado::falha(422, 'pessoa_inativa', ['mensagem' => 'Reative a pessoa antes de mudar o vínculo.']);
        }

        $atual = $estado['vinculo'] ?? Vinculo::Visitante;
        try {
            $atual->avancarPara($destino);
        } catch (TransicaoVinculoInvalida $recusa) {
            return Resultado::falha(422, 'transicao_vinculo_invalida', [
                'mensagem' => $recusa->getMessage(),
                'proximo' => $recusa->proximo?->value,
            ]);
        }

        $ator = $this->alcance->ator();
        if ($destino->temMatricula()) {
            if ($this->organizacoes->modoMatricula($ator->organizacaoId) === 'automatica') {
                return Resultado::falha(422, 'matricula_automatica_indisponivel', [
                    'mensagem' => 'A matrícula automática ainda não está disponível. Mude o modo para manual na configuração.',
                ]);
            }
            if ($matricula === null) {
                return Resultado::falha(422, 'matricula_obrigatoria', ['mensagem' => 'Informe a matrícula para passar a Membro.']);
            }
            if ($this->busca->matriculaEmUso($matricula, $pessoa['id'])) {
                return Resultado::falha(422, 'matricula_em_uso', ['mensagem' => 'Essa matrícula já é de outra pessoa da organização.']);
            }
        }

        $this->vinculos->alterarVinculo($pessoa['id'], $destino, $destino->temMatricula() ? $matricula : null);
        $this->vinculos->registrarEvento($ator->organizacaoId, $pessoa['id'], 'vinculo_alterado', $motivoId, [
            'de' => $atual->value,
            'para' => $destino->value,
        ], $ator->acessoId);

        $campos = ['vinculo'];
        if ($destino->temMatricula()) {
            $campos[] = 'matricula';
        }
        if ($estado['novo_convertido']) {
            $campos[] = 'novo_convertido';
        }
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaAtualizada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
            campos: $campos,
        );

        return Resultado::ok();
    }

    private function negar(Permissao $permissao): Resultado
    {
        Log::info('authz.denied', ['permissao' => $permissao->value]);

        return Resultado::falha(403, 'authz.denied');
    }
}
