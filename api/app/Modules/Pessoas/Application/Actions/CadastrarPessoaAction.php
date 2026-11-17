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
use Illuminate\Support\Facades\Log;

/**
 * Cadastro rápido (CA-13; RN-01; RN-23): salva a ficha mínima, grava o evento `entrada` com "como chegou"
 * como motivo e os pares que a pessoa marcou como "é outra pessoa". Obrigatórios do vínculo (BR-011) não
 * bloqueiam aqui: entram na completude. CPF igual a outro da organização, inclusive na lixeira, é 422.
 */
#[RequerPermissao(Permissao::PessoasCriar)]
#[AuditaCom(EventoAuditoria::PessoaCriada)]
final readonly class CadastrarPessoaAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private BuscaDenominacao $busca,
        private CadastroDePessoas $cadastro,
        private Auditoria $auditoria,
    ) {}

    /** @param list<string> $naoDuplicadas uuids que a pessoa confirmou serem outras pessoas */
    public function executar(DadosCadastroRapido $dados, array $naoDuplicadas = []): Resultado
    {
        if (! $this->alcance->pode(Permissao::PessoasCriar, $dados->igrejaId)) {
            Log::info('authz.denied', ['permissao' => Permissao::PessoasCriar->value]);

            return Resultado::falha(403, 'authz.denied');
        }

        if ($dados->cpf !== null && $this->busca->cpfEmUso($dados->cpf)) {
            return Resultado::falha(422, 'cpf_em_uso');
        }

        $ator = $this->alcance->ator();
        $pessoa = $this->cadastro->cadastrar($ator->organizacaoId, $dados, $ator->acessoId);

        foreach (array_unique($naoDuplicadas) as $uuid) {
            $outraId = $this->busca->idPorUuid($uuid);
            if ($outraId !== null && $outraId !== $pessoa['id']) {
                $this->cadastro->marcarNaoDuplicadas($ator->organizacaoId, $pessoa['id'], $outraId, $ator->acessoId);
            }
        }

        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaCriada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $dados->igrejaId,
            campos: $dados->camposPreenchidos(),
        );

        return Resultado::ok(['pessoa' => ['uuid' => $pessoa['uuid']]], 201);
    }
}
