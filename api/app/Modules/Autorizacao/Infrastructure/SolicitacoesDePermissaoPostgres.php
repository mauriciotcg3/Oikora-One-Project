<?php

namespace App\Modules\Autorizacao\Infrastructure;

use App\Modules\Autorizacao\Application\Solicitacoes\SolicitacoesDePermissao;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\ItemSolicitado;
use App\Modules\Autorizacao\Domain\Permissao;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Tabelas da OIK-ADR-0012 sob a RLS do contexto; os itens repetem a organização (FK composta). */
final class SolicitacoesDePermissaoPostgres implements SolicitacoesDePermissao
{
    public function criar(int $organizacaoId, int $solicitanteAcessoId, array $itens, int $duracaoHoras, string $justificativa): array
    {
        $uuid = (string) Str::uuid();
        $id = (int) DB::table('solicitacoes_permissao')->insertGetId([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'solicitante_acesso_id' => $solicitanteAcessoId,
            'estado' => 'pendente',
            'justificativa' => $justificativa,
            'duracao_horas' => $duracaoHoras,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->gravarItens('solicitacao_permissoes', 'solicitacao_id', $organizacaoId, $id, $itens);

        return ['id' => $id, 'uuid' => $uuid];
    }

    public function travarPorUuid(string $uuid): ?array
    {
        $linha = DB::table('solicitacoes_permissao')->where('uuid', $uuid)->lockForUpdate()->first();
        if ($linha === null) {
            return null;
        }

        return [
            'id' => (int) $linha->id,
            'uuid' => (string) $linha->uuid,
            'solicitante_acesso_id' => (int) $linha->solicitante_acesso_id,
            'estado' => (string) $linha->estado,
            'duracao_horas' => (int) $linha->duracao_horas,
            'versao' => (int) $linha->versao,
            'itens' => $this->itens('solicitacao_permissoes', 'solicitacao_id', (int) $linha->id),
        ];
    }

    public function decidir(int $id, string $estado, ?int $decididaPorAcessoId, ?string $motivo): void
    {
        DB::table('solicitacoes_permissao')->where('id', $id)->update([
            'estado' => $estado,
            'decidida_por_acesso_id' => $decididaPorAcessoId,
            'decidida_em' => now(),
            'motivo_decisao' => $motivo,
            'versao' => DB::raw('versao + 1'),
            'updated_at' => now(),
        ]);
    }

    public function conceder(int $organizacaoId, int $solicitacaoId, int $acessoId, array $itens, int $duracaoHoras): array
    {
        $uuid = (string) Str::uuid();
        $inicio = now();
        $expira = $inicio->copy()->addHours($duracaoHoras);
        $id = (int) DB::table('concessoes_permissao')->insertGetId([
            'uuid' => $uuid,
            'organizacao_id' => $organizacaoId,
            'solicitacao_id' => $solicitacaoId,
            'acesso_id' => $acessoId,
            'inicia_em' => $inicio,
            'expira_em' => $expira,
            'created_at' => $inicio,
            'updated_at' => $inicio,
        ]);
        $this->gravarItens('concessao_permissoes', 'concessao_id', $organizacaoId, $id, $itens);

        return ['id' => $id, 'uuid' => $uuid, 'expira_em' => self::instante($expira)];
    }

    public function travarConcessaoVigente(string $uuid): ?array
    {
        $linha = DB::table('concessoes_permissao')
            ->where('uuid', $uuid)
            ->whereNull('revogada_em')
            ->whereRaw('expira_em > now()')
            ->lockForUpdate()
            ->first();
        if ($linha === null) {
            return null;
        }

        return [
            'id' => (int) $linha->id,
            'uuid' => (string) $linha->uuid,
            'solicitacao_id' => (int) $linha->solicitacao_id,
            'acesso_id' => (int) $linha->acesso_id,
            'expira_em' => self::instante(Carbon::parse((string) $linha->expira_em)),
            'itens' => $this->itens('concessao_permissoes', 'concessao_id', (int) $linha->id),
        ];
    }

    public function revogar(int $concessaoId, int $revogadaPorAcessoId, ?string $motivo): void
    {
        DB::table('concessoes_permissao')->where('id', $concessaoId)->update([
            'revogada_em' => now(),
            'revogada_por_acesso_id' => $revogadaPorAcessoId,
            'motivo_revogacao' => $motivo,
            'updated_at' => now(),
        ]);
    }

    public function listar(int $acessoId, string $visao): array
    {
        $consulta = DB::table('solicitacoes_permissao')->orderByDesc('id')->limit(100);
        $visao === 'aprovaveis'
            ? $consulta->where('estado', 'pendente')->where('solicitante_acesso_id', '<>', $acessoId)
            : $consulta->where('solicitante_acesso_id', $acessoId);

        return array_values(array_filter(array_map(
            fn (object $linha): ?array => $this->detalhar((string) $linha->uuid),
            $consulta->get(['uuid'])->all(),
        )));
    }

    public function detalhar(string $uuid): ?array
    {
        $linha = DB::table('solicitacoes_permissao as s')
            ->join('acessos as a', function ($join): void {
                $join->on('a.organizacao_id', '=', 's.organizacao_id')->on('a.id', '=', 's.solicitante_acesso_id');
            })
            ->where('s.uuid', $uuid)
            ->first(['s.*', 'a.uuid as solicitante_uuid']);
        if ($linha === null) {
            return null;
        }

        $concessao = DB::table('concessoes_permissao')->where('solicitacao_id', $linha->id)->first();

        return [
            'uuid' => (string) $linha->uuid,
            'estado' => (string) $linha->estado,
            'versao' => (int) $linha->versao,
            'solicitante' => ['acesso' => (string) $linha->solicitante_uuid],
            'duracao_horas' => (int) $linha->duracao_horas,
            'justificativa' => (string) $linha->justificativa,
            'motivo_decisao' => $linha->motivo_decisao,
            'criada_em' => (string) $linha->created_at,
            'decidida_em' => $linha->decidida_em,
            'itens' => $this->itens('solicitacao_permissoes', 'solicitacao_id', (int) $linha->id),
            'concessao' => $concessao === null ? null : [
                'uuid' => (string) $concessao->uuid,
                'inicia_em' => (string) $concessao->inicia_em,
                'expira_em' => (string) $concessao->expira_em,
                'revogada_em' => $concessao->revogada_em,
            ],
        ];
    }

    /** Instante em UTC sem fuso no texto (`2026-11-17T14:00:00Z`), aceito como metadado de auditoria. */
    private static function instante(CarbonInterface $momento): string
    {
        return $momento->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** @param list<ItemSolicitado> $itens */
    private function gravarItens(string $tabela, string $chave, int $organizacaoId, int $paiId, array $itens): void
    {
        DB::table($tabela)->insert(array_map(static fn (ItemSolicitado $item): array => [
            'organizacao_id' => $organizacaoId,
            $chave => $paiId,
            'permissao' => $item->permissao->value,
            'escopo_tipo' => $item->escopoTipo->value,
            'escopo_id' => $item->escopoId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $itens));
    }

    /** @return list<ItemSolicitado> */
    private function itens(string $tabela, string $chave, int $paiId): array
    {
        return array_values(DB::table($tabela)->where($chave, $paiId)->orderBy('id')->get()->map(
            static fn (object $linha): ItemSolicitado => new ItemSolicitado(
                Permissao::from((string) $linha->permissao),
                EscopoTipo::from((string) $linha->escopo_tipo),
                $linha->escopo_id === null ? null : (int) $linha->escopo_id,
            ),
        )->all());
    }
}
