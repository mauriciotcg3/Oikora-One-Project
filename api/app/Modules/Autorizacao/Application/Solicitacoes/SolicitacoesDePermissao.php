<?php

namespace App\Modules\Autorizacao\Application\Solicitacoes;

use App\Modules\Autorizacao\Domain\ItemSolicitado;

/** Persistência das solicitações e concessões temporárias (OIK-ADR-0012 §11), sob a RLS do contexto. */
interface SolicitacoesDePermissao
{
    /**
     * @param  list<ItemSolicitado>  $itens
     * @return array{id: int, uuid: string}
     */
    public function criar(int $organizacaoId, int $solicitanteAcessoId, array $itens, int $duracaoHoras, string $justificativa): array;

    /**
     * Lê e trava a linha da solicitação até o fim da transação (decisões concorrentes esperam).
     *
     * @return array{id: int, uuid: string, solicitante_acesso_id: int, estado: string, duracao_horas: int, versao: int, itens: list<ItemSolicitado>}|null
     */
    public function travarPorUuid(string $uuid): ?array;

    public function decidir(int $id, string $estado, ?int $decididaPorAcessoId, ?string $motivo): void;

    /**
     * @param  list<ItemSolicitado>  $itens
     * @return array{id: int, uuid: string, expira_em: string}
     */
    public function conceder(int $organizacaoId, int $solicitacaoId, int $acessoId, array $itens, int $duracaoHoras): array;

    /**
     * Concessão vigente travada até o fim da transação, ou null (inexistente, vencida ou revogada).
     *
     * @return array{id: int, uuid: string, solicitacao_id: int, acesso_id: int, expira_em: string, itens: list<ItemSolicitado>}|null
     */
    public function travarConcessaoVigente(string $uuid): ?array;

    public function revogar(int $concessaoId, int $revogadaPorAcessoId, ?string $motivo): void;

    /**
     * @param  'proprias'|'aprovaveis'  $visao
     * @return list<array<string, mixed>>
     */
    public function listar(int $acessoId, string $visao): array;

    /** @return array<string, mixed>|null */
    public function detalhar(string $uuid): ?array;
}
