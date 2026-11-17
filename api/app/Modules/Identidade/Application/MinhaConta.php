<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Autorizacao\Contracts\PapeisDaConta;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

/**
 * `GET /me` e `PUT /me/organizacao-ativa` (OIK-ADR-0003 §12; T-16). A organização ativa fica na sessão e
 * é revalidada contra os acessos ativos; as capacidades são calculadas do alcance, sem cache.
 */
final readonly class MinhaConta
{
    public function __construct(
        private Contas $contas,
        private AcessosDaConta $acessosDaConta,
        private AcessosDaOrganizacao $acessos,
        private PapeisDaConta $papeis,
        private ExigenciaDeDoisFatores $exigencia,
    ) {}

    /** @return array<string, mixed> */
    public function consultar(int $contaId, Session $sessao): array
    {
        $organizacoes = $this->acessosDaConta->organizacoes($contaId);
        $ativa = $this->organizacaoAtiva($organizacoes, $sessao);

        $perfil = $this->contas->perfil($contaId);

        // Formato do bootstrap da sessão em `contrato-permissoes.md`: ids públicos (uuid) e `autorizacao`
        // sempre presente — sem ela, o front trata como falha de contrato, nunca como acesso total.
        return [
            'conta' => $perfil === null ? null : ['id' => $perfil['uuid'], ...array_diff_key($perfil, ['uuid' => true])],
            'organizacao_ativa' => $ativa === null ? null : ['id' => $ativa->uuid, 'nome' => $ativa->nome],
            'organizacoes' => array_map(static fn (OrganizacaoDaConta $o): array => ['id' => $o->uuid, 'nome' => $o->nome], $organizacoes),
            'autorizacao' => ['capacidades' => $ativa === null ? [] : $this->capacidades($contaId, $ativa->id)],
            'elevacao' => SessaoDoPainel::estadoDaElevacao($sessao),
            'dois_fatores' => [
                'canal' => $this->contas->segundoFator($contaId)?->canal,
                'obrigatorio' => $this->exigencia->obrigatorio($contaId),
            ],
        ];
    }

    /** @return bool false quando a conta não tem acesso ativo na organização */
    public function escolherOrganizacao(int $contaId, string $organizacaoUuid, Session $sessao): bool
    {
        foreach ($this->acessosDaConta->organizacoes($contaId) as $organizacao) {
            if ($organizacao->uuid === $organizacaoUuid) {
                SessaoDoPainel::escolherOrganizacao($sessao, $organizacao->id);

                return true;
            }
        }

        return false;
    }

    /** @param list<OrganizacaoDaConta> $organizacoes */
    private function organizacaoAtiva(array $organizacoes, Session $sessao): ?OrganizacaoDaConta
    {
        $guardada = SessaoDoPainel::organizacao($sessao);

        foreach ($organizacoes as $organizacao) {
            if ($organizacao->id === $guardada) {
                return $organizacao;
            }
        }

        if ($guardada !== null) {
            SessaoDoPainel::esquecerOrganizacao($sessao);
        }

        if (count($organizacoes) === 1) {
            SessaoDoPainel::escolherOrganizacao($sessao, $organizacoes[0]->id);

            return $organizacoes[0];
        }

        return null;
    }

    /** @return list<array{permissao: string, escopo_tipo: string, escopo_ids: list<string>}> */
    private function capacidades(int $contaId, int $organizacaoId): array
    {
        return DB::transaction(function () use ($contaId, $organizacaoId): array {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);
            $acessoId = $this->acessos->ativoDaConta($contaId);

            return $acessoId === null ? [] : $this->papeis->capacidades($organizacaoId, $acessoId);
        });
    }
}
