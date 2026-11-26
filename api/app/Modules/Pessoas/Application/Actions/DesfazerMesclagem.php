<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Contracts\OperacoesDesfaziveis;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Mesclagem\Mesclagens;
use Carbon\CarbonImmutable;

#[RequerPermissao(Permissao::PessoasMesclar)]
#[AuditaCom(EventoAuditoria::PessoaMesclagemDesfeita)]
final readonly class DesfazerMesclagem
{
    public function __construct(
        private AlcanceAtual $alcance,
        private OperacoesDesfaziveis $operacoes,
        private Mesclagens $mesclagens,
        private AcessosDaOrganizacao $acessos,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $operacaoUuid): Resultado
    {
        $operacao = $this->operacoes->porUuid($operacaoUuid, true);
        if ($operacao === null || $operacao['tipo'] !== 'mesclagem') {
            return Resultado::falha(404, 'operacao_nao_encontrada');
        }
        if ($operacao['estado'] !== 'executada') {
            return Resultado::falha(422, 'operacao_ja_encerrada');
        }
        if (CarbonImmutable::parse($operacao['expira_em'])->isPast() || $operacao['snapshot'] === null) {
            return Resultado::falha(422, 'operacao_expirada');
        }

        $snapshot = $operacao['snapshot'];
        /** @var array<string, mixed> $mantida */
        $mantida = $snapshot['mantida'];
        /** @var array<string, mixed> $absorvida */
        $absorvida = $snapshot['absorvida'];
        foreach ([(int) $mantida['igreja_id'], (int) $absorvida['igreja_id']] as $igrejaId) {
            if (! $this->alcance->pode(Permissao::PessoasMesclar, $igrejaId)) {
                return Resultado::falha(403, 'authz.denied');
            }
        }

        $ator = $this->alcance->ator();
        $conflitos = $this->mesclagens->desfazer($snapshot, $operacao['afetados']);
        $acessoMovido = $operacao['afetados']['acesso_movido_id'] ?? null;
        if (is_int($acessoMovido)) {
            $this->acessos->religarPessoa($acessoMovido, (int) $operacao['afetados']['absorvida_id']);
        }
        $this->operacoes->encerrar($operacao['id'], 'desfeita', $ator->acessoId);

        foreach ([(int) $operacao['afetados']['mantida_id'], (int) $operacao['afetados']['absorvida_id']] as $pessoaId) {
            $this->mesclagens->evento($ator->organizacaoId, $pessoaId, 'mesclagem_desfeita', $ator->acessoId, ['operacao' => $operacaoUuid]);
        }
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaMesclagemDesfeita,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: (string) $mantida['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: (int) $mantida['igreja_id'],
            campos: $conflitos,
            metadados: [
                'operacao_id' => $operacaoUuid,
                'pessoa_origem_id' => (string) $absorvida['uuid'],
                'pessoa_destino_id' => (string) $mantida['uuid'],
                'acesso_movido' => is_int($acessoMovido),
            ],
        );

        return Resultado::ok(['operacao' => $operacaoUuid, 'estado' => 'desfeita', 'conflitos' => $conflitos]);
    }
}
