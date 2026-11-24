<?php

namespace App\Modules\Identidade\Application;

use Closure;
use DateTimeImmutable;

/**
 * Persistência de convites e acessos (OIK-ADR-0003 §2, §5; T-22). Tudo sob a RLS da organização, menos
 * as duas vias estreitas do aceite: a organização pelo hash do token e a criação da conta.
 *
 * @phpstan-type ConviteLido array{id: int, uuid: string, pessoa_id: int, papel_id: int, escopo_tipo: string, escopo_id: int|null, estado: string, expira_em: string}
 */
interface Convites
{
    /** Há convite `enviado` e ainda no prazo para a Pessoa. */
    public function pendente(int $pessoaId): bool;

    /** @return array{id: int, uuid: string} */
    public function emitir(int $organizacaoId, int $pessoaId, int $papelId, string $escopoTipo, ?int $escopoId, string $tokenHash, DateTimeImmutable $expiraEm, int $emissorAcessoId): array;

    /**
     * Lê e bloqueia o convite até o fim da transação.
     *
     * @return ConviteLido|null
     */
    public function porUuid(string $uuid): ?array;

    /** @return ConviteLido|null lido e bloqueado pelo hash do token */
    public function doToken(string $tokenHash): ?array;

    public function revogar(int $id, int $porAcessoId): void;

    public function aceitar(int $id, int $acessoId): void;

    /** Marca `expirado` os convites enviados que passaram do prazo, em todas as organizações. */
    public function expirarVencidos(): int;

    /** Via estreita: a organização do convite, antes de haver contexto. */
    public function organizacaoDoToken(string $tokenHash): ?int;

    /**
     * Roda `$acao` numa transação com a organização (e, se houver, a conta) no contexto da RLS.
     *
     * @template T
     *
     * @param  Closure(): T  $acao
     * @return T
     */
    public function naOrganizacao(int $organizacaoId, ?int $contaId, Closure $acao): mixed;

    /** Grava a conta no contexto da RLS, depois de criá-la no aceite. */
    public function usarConta(int $contaId): void;

    public function pessoaTemAcesso(int $pessoaId): bool;

    public function contaTemAcesso(int $contaId): bool;

    /** @return array{id: int, uuid: string} */
    public function criarAcesso(int $organizacaoId, int $contaId, int $pessoaId): array;

    /** @return array{id: int, uuid: string, conta_id: int, ativo: bool}|null */
    public function acessoPorUuid(string $uuid): ?array;

    public function revogarAcesso(int $id, int $porAcessoId): void;

    /** Via estreita: cria a conta com a senha que a própria pessoa definiu (RN-12). */
    public function criarConta(?string $email, ?string $celular, string $hashSenha): int;

    public function identificadorEmUso(string $identificador): bool;
}
