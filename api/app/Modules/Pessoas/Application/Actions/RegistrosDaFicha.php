<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Pessoas\Domain\TipoRelacionamento;
use DateTimeImmutable;

/**
 * Persistência da linha do tempo manual, dos relacionamentos, das funções e do tipo de membro (T-29),
 * sempre na transação da requisição, com o contexto de RLS já gravado.
 */
interface RegistrosDaFicha
{
    /** @return array{uuid: string} */
    public function registrarManual(int $organizacaoId, int $pessoaId, DateTimeImmutable $ocorridoEm, string $descricao, int $autorAcessoId): array;

    /**
     * Linha do tempo em ordem de `ocorrido_em`, mais recente primeiro.
     *
     * @return list<array{uuid: string, tipo: string, ocorrido_em: string, motivo_id: int|null, motivo_texto: string|null, autor_acesso_id: int|null, origem: string, dados: array<string, mixed>|null}>
     */
    public function linhaDoTempo(int $pessoaId): array;

    public function relacionamentoExiste(int $pessoaId, int $relacionadaId, TipoRelacionamento $tipo): bool;

    /**
     * Grava a relação e a inversa com o mesmo `par_uuid`.
     *
     * @return array{uuid: string}
     */
    public function relacionar(int $organizacaoId, int $pessoaId, int $relacionadaId, TipoRelacionamento $tipo, ?string $descricao, int $autorAcessoId): array;

    /** Apaga a relação e a inversa; false se o relacionamento não é daquela pessoa. */
    public function removerRelacionamento(int $pessoaId, string $uuid): bool;

    /** @return list<array{uuid: string, tipo: string, descricao: string|null, relacionada_id: int}> */
    public function relacionamentos(int $pessoaId): array;

    /** @return array{uuid: string} */
    public function atribuirFuncao(int $organizacaoId, int $pessoaId, int $funcaoId, DateTimeImmutable $desde, ?DateTimeImmutable $ate, int $autorAcessoId): array;

    /** @return array{id: int, desde: string, ate: string|null}|null */
    public function funcao(int $pessoaId, string $uuid): ?array;

    public function encerrarFuncao(int $id, DateTimeImmutable $ate): void;

    /** @return list<array{uuid: string, funcao_id: int, desde: string, ate: string|null}> */
    public function funcoes(int $pessoaId): array;

    public function tipoMembro(int $pessoaId): ?int;

    public function definirTipoMembro(int $pessoaId, ?int $tipoMembroId): void;
}
