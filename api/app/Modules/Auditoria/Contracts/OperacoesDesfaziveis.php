<?php

namespace App\Modules\Auditoria\Contracts;

/**
 * Operações desfazíveis (OIK-ADR-0005 §6): quem executa uma operação em massa (mesclagem; depois
 * importação e migração) grava aqui o snapshot do antes, cifrado, válido por 90 dias. Roda na
 * organização em contexto (RLS).
 *
 * @phpstan-type Operacao array{id: int, uuid: string, tipo: string, estado: string, executada_em: string, expira_em: string, snapshot: array<string, mixed>|null, afetados: array<string, mixed>}
 */
interface OperacoesDesfaziveis
{
    public const VALIDADE_DIAS = 90;

    /**
     * @param  array<string, mixed>  $snapshot  pode conter dado pessoal: só é gravado cifrado
     * @param  array<string, mixed>  $afetados  ids internos, sem dado pessoal
     * @return array{id: int, uuid: string, expira_em: string}
     */
    public function registrar(int $organizacaoId, string $tipo, ?int $autorAcessoId, array $snapshot, array $afetados): array;

    /** @return Operacao|null lida com o snapshot decifrado; `FOR UPDATE` quando $bloquear */
    public function porUuid(string $uuid, bool $bloquear = false): ?array;

    /** Desfeita ou expirada: o snapshot é apagado. */
    public function encerrar(int $id, string $estado, ?int $porAcessoId = null): void;

    /**
     * Valores de `afetados[$chave]` das operações do tipo ainda `executada` (ex.: fichas absorvidas que a
     * purga da lixeira não pode apagar, U-33).
     *
     * @return list<int>
     */
    public function protegidos(string $tipo, string $chave): array;

    /** @return list<Operacao> Operações executadas cujo prazo já terminou, bloqueadas para atualização. */
    public function vencidas(string $tipo): array;

    /**
     * Encerra como `expirada` (snapshot apagado) toda operação ainda `executada` em que a Pessoa é
     * `mantida_id` ou `absorvida_id` (removedor LGPD, OIK-ADR-0005 §8). @return int quantas
     */
    public function expirarDaPessoa(int $pessoaId): int;
}
