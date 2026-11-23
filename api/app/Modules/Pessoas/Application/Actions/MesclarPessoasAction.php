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
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Mesclagem\Mesclagens;
use App\Modules\Pessoas\Domain\CampoFicha;

/**
 * Mesclagem manual campo a campo (P FR-016; RN-11; U-27, U-30, U-33; CA-21; plan.md §6). Exige
 * `pessoas.mesclar` nas igrejas das duas. Grava o snapshot das duas fichas e do que mudou de dono numa
 * operação desfazível por 90 dias, aplica os valores escolhidos, move os filhos (e o acesso, se só a
 * absorvida tem), marca a absorvida como excluída por `mesclagem` sem CPF e matrícula, e grava eventos
 * e auditoria só com nomes de campo.
 */
#[RequerPermissao(Permissao::PessoasMesclar)]
#[AuditaCom(EventoAuditoria::PessoaMesclada)]
final readonly class MesclarPessoasAction
{
    /** Escrever valor de categoria sensível na mantida exige o `…editar` dela (OIK-ADR-0004 §7). */
    private const EDICAO = [
        'saude' => Permissao::SaudeEditar,
        'documentos' => Permissao::DocumentosEditar,
        'fe' => Permissao::FeEditar,
    ];

    public function __construct(
        private AlcanceAtual $alcance,
        private Mesclagens $mesclagens,
        private BuscaDenominacao $busca,
        private AcessosDaOrganizacao $acessos,
        private OperacoesDesfaziveis $operacoes,
        private Auditoria $auditoria,
    ) {}

    /** @param  array<string, string>  $valores  campo → `mantida` ou `absorvida` */
    public function executar(string $mantidaUuid, string $absorvidaUuid, array $valores): Resultado
    {
        if ($mantidaUuid === $absorvidaUuid) {
            return Resultado::invalido(['absorvida' => 'mesma_pessoa']);
        }

        $fichas = $this->mesclagens->bloquear([$mantidaUuid, $absorvidaUuid]);
        foreach (['mantida' => $mantidaUuid, 'absorvida' => $absorvidaUuid] as $papel => $uuid) {
            if (! isset($fichas[$uuid])) {
                return $this->busca->existe($uuid) ? $this->negar(Permissao::PessoasVer) : Resultado::invalido([$papel => 'pessoa_inexistente']);
            }
        }
        $mantida = $fichas[$mantidaUuid];
        $absorvida = $fichas[$absorvidaUuid];
        $mantidaId = (int) $mantida['id'];
        $absorvidaId = (int) $absorvida['id'];

        foreach ([(int) $mantida['igreja_id'], (int) $absorvida['igreja_id']] as $igrejaId) {
            if (! $this->alcance->pode(Permissao::PessoasMesclar, $igrejaId)) {
                return $this->negar(Permissao::PessoasMesclar);
            }
        }

        // Uma Pessoa por conta (BR-002): com as duas com acesso, o administrador revoga um antes (U-27).
        $acessoMantida = $this->acessos->ativoDaPessoa($mantidaId);
        $acessoAbsorvida = $this->acessos->ativoDaPessoa($absorvidaId);
        if ($acessoMantida !== null && $acessoAbsorvida !== null) {
            return Resultado::falha(422, 'mesclagem_contas_conflitantes', [
                'mensagem' => 'As duas pessoas têm acesso ao painel. Revogue um dos acessos antes de mesclar.',
            ]);
        }

        $daAbsorvida = [];
        $erros = [];
        foreach ($valores as $campo => $origem) {
            $definicao = CampoFicha::tryFrom($campo);
            if ($definicao === null || $definicao->tabela() !== 'pessoas') {
                $erros["valores.{$campo}"] = 'campo_invalido';

                continue;
            }
            if ($origem !== 'absorvida') {
                continue;
            }
            $permissao = self::EDICAO[$definicao->categoria()] ?? null;
            if ($permissao !== null && ! $this->alcance->pode($permissao, (int) $mantida['igreja_id'])) {
                $erros["valores.{$campo}"] = 'sem_permissao_categoria';

                continue;
            }
            $daAbsorvida[] = $campo;
        }
        if ($erros !== []) {
            return Resultado::invalido($erros);
        }

        $ator = $this->alcance->ator();
        $snapshot = [
            'mantida' => $mantida,
            'absorvida' => $absorvida,
            'filhos_mantida' => $this->mesclagens->filhos($mantidaId),
            'filhos_absorvida' => $this->mesclagens->filhos($absorvidaId),
            'campos_da_absorvida' => $daAbsorvida,
        ];

        $this->mesclagens->aplicar($mantidaId, $absorvidaId, $daAbsorvida);
        $movidos = $this->mesclagens->moverFilhos($absorvidaId, $mantidaId);
        $acessoMovido = null;
        if ($acessoAbsorvida !== null) {
            $this->acessos->religarPessoa($acessoAbsorvida, $mantidaId);
            $acessoMovido = $acessoAbsorvida;
        }
        $this->mesclagens->marcarAbsorvida($absorvidaId, $ator->acessoId);

        $operacao = $this->operacoes->registrar($ator->organizacaoId, 'mesclagem', $ator->acessoId, $snapshot, [
            'mantida_id' => $mantidaId,
            'absorvida_id' => $absorvidaId,
            'acesso_movido_id' => $acessoMovido,
            'movidos' => $movidos,
        ]);

        foreach ([$mantidaId => $absorvidaUuid, $absorvidaId => $mantidaUuid] as $pessoaId => $outra) {
            $this->mesclagens->evento($ator->organizacaoId, $pessoaId, 'mesclagem', $ator->acessoId, [
                'operacao' => $operacao['uuid'],
                'outra_pessoa' => $outra,
                'papel' => $pessoaId === $mantidaId ? 'mantida' : 'absorvida',
            ]);
        }
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaMesclada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $mantidaUuid,
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: (int) $mantida['igreja_id'],
            campos: $daAbsorvida,
            metadados: [
                'operacao_id' => $operacao['uuid'],
                'pessoa_origem_id' => $absorvidaUuid,
                'pessoa_destino_id' => $mantidaUuid,
                'acesso_movido' => $acessoMovido !== null,
            ],
        );

        return Resultado::ok([
            'operacao' => $operacao['uuid'],
            'mantida' => $mantidaUuid,
            'absorvida' => $absorvidaUuid,
            'expira_em' => $operacao['expira_em'],
        ], 201);
    }

    private function negar(Permissao $permissao): Resultado
    {
        return FichaParaEditar::negar($permissao);
    }
}
