<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use Illuminate\Support\Facades\DB;

/**
 * `GET /painel/acessos` (plan.md §7; T-40): acessos da organização em contexto, com a Pessoa ligada e os
 * convites ainda válidos. Governança: exige `acessos.gerir` no escopo da organização (como emitir e
 * revogar, T-22). Sem contato da Pessoa na resposta.
 */
final readonly class ListarAcessos
{
    public function __construct(
        private AlcanceAtual $alcance,
        private ContatosDaPessoa $pessoas,
    ) {}

    public function permitido(): bool
    {
        return $this->alcance->temNaOrganizacao(Permissao::AcessosGerir);
    }

    /** @return array{acessos: list<array<string, mixed>>, convites: list<array<string, mixed>>} */
    public function executar(): array
    {
        $acessos = DB::table('acessos')->orderByRaw("estado = 'ativo' desc")->orderBy('id')
            ->get(['id', 'uuid', 'pessoa_id', 'estado', 'created_at', 'revogado_em']);
        $convites = DB::table('convites')->where('estado', 'enviado')->where('expira_em', '>', now())->orderBy('id')
            ->get(['uuid', 'pessoa_id', 'expira_em', 'created_at']);

        $pessoa = fn (int $id): array => ['uuid' => $this->pessoas->uuid($id), 'nome' => $this->pessoas->nome($id)];
        /** @var array<int, list<array{nome: string, escopo_tipo: string}>> $papeis */
        $papeis = [];
        $atribuicoes = DB::table('atribuicoes_papel as a')->join('papeis as p', 'p.id', '=', 'a.papel_id')
            ->whereIn('a.acesso_id', $acessos->pluck('id'))
            ->get(['a.acesso_id', 'p.nome', 'a.escopo_tipo']);
        foreach ($atribuicoes as $atribuicao) {
            $papeis[(int) $atribuicao->acesso_id][] = ['nome' => (string) $atribuicao->nome, 'escopo_tipo' => (string) $atribuicao->escopo_tipo];
        }

        return [
            'acessos' => array_values($acessos->map(static fn (object $acesso): array => [
                'uuid' => (string) $acesso->uuid,
                'estado' => (string) $acesso->estado,
                'pessoa' => $pessoa((int) $acesso->pessoa_id),
                'papeis' => $papeis[(int) $acesso->id] ?? [],
                'criado_em' => (string) $acesso->created_at,
                'meta' => ['acoes' => $acesso->estado === 'ativo' ? ['revogar'] : []],
            ])->all()),
            'convites' => array_values($convites->map(static fn (object $convite): array => [
                'uuid' => (string) $convite->uuid,
                'pessoa' => $pessoa((int) $convite->pessoa_id),
                'expira_em' => (string) $convite->expira_em,
                'meta' => ['acoes' => ['revogar']],
            ])->all()),
        ];
    }
}
