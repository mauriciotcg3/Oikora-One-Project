<?php

namespace App\Modules\Pessoas\Application\Transferencias;

/** Persistência dos pedidos de transferência (T-34), na transação da requisição, sob a RLS. */
interface Transferencias
{
    /**
     * Pessoa da organização, sem o filtro de alcance: a secretaria de destino pede por quem ainda não vê.
     *
     * @return array{id: int, uuid: string, igreja_id: int}|null
     */
    public function pessoa(string $uuid): ?array;

    public function temPendente(int $pessoaId): bool;

    /** @return array{id: int, uuid: string} */
    public function solicitar(int $organizacaoId, int $pessoaId, int $origemId, int $destinoId, int $solicitanteAcessoId): array;

    /**
     * Lê e bloqueia o pedido até o fim da transação (decisões concorrentes ficam em série).
     *
     * @return array{id: int, uuid: string, pessoa_id: int, pessoa_uuid: string, igreja_origem_id: int, igreja_destino_id: int, estado: string, solicitada_por_acesso_id: int}|null
     */
    public function paraDecidir(string $uuid): ?array;

    public function aprovar(int $id, int $decisorAcessoId): void;

    public function recusar(int $id, int $decisorAcessoId, string $justificativa): void;

    /** Muda a igreja da pessoa; vínculo e matrícula ficam como estão (RN-08). */
    public function moverPessoa(int $pessoaId, int $destinoId): void;

    /** @param  array<string, string|null>  $dados */
    public function registrarEvento(int $organizacaoId, int $pessoaId, array $dados, int $autorAcessoId): void;

    /**
     * Pedidos em que uma das igrejas está no conjunto dado (destino de quem pede ou origem de quem aprova).
     *
     * @param  list<int>  $origens
     * @param  list<int>  $destinos
     * @return list<array{uuid: string, pessoa_uuid: string, pessoa_nome: string, igreja_origem_id: int, igreja_destino_id: int, estado: string, justificativa: string|null, created_at: string, decidida_em: string|null}>
     */
    public function visiveis(array $origens, array $destinos): array;
}
