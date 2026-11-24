<?php

namespace App\Modules\Pessoas\Application\Lixeira;

/**
 * Persistência da lixeira (OIK-ADR-0005 §7; RN-19). Lê pelo filtro de alcance de Pessoa; a ficha
 * absorvida por mesclagem não é da lixeira (só sai pelo desfazer ou pela expiração, U-33).
 */
interface Lixeira
{
    /** @return array{id: int, uuid: string, igreja_id: int, excluida_em: string|null, exclusao_motivo: string|null}|null */
    public function localizar(string $uuid, bool $bloquear = false): ?array;

    public function excluir(int $pessoaId, string $motivo, int $porAcessoId): void;

    public function recuperar(int $pessoaId): void;

    /**
     * @param  list<int>  $igrejas
     * @return array{itens: list<array{uuid: string, nome: string, igreja_id: int, excluida_em: string, motivo: string|null}>, total: int}
     */
    public function listar(array $igrejas, int $pagina, int $porPagina): array;

    /**
     * Fichas da organização em contexto vencidas na lixeira e ainda não removidas, sem as protegidas.
     *
     * @param  list<int>  $protegidas
     * @return array<int, string> id → uuid
     */
    public function vencidas(int $dias, array $protegidas): array;

    /** @param  array<string, mixed>  $dados */
    public function evento(int $organizacaoId, int $pessoaId, string $tipo, ?int $autorAcessoId, array $dados = []): void;
}
