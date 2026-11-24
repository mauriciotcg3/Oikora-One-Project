<?php

namespace App\Modules\Identidade\Contracts;

/**
 * Leitura dos acessos da organização em contexto (RLS). Identidade é dona de `acessos`; os outros
 * módulos perguntam por aqui (MOD-002, DB-001).
 */
interface AcessosDaOrganizacao
{
    /** Acesso ativo da conta na organização do contexto, ou null. */
    public function ativoDaConta(int $contaId): ?int;

    /** @return array{id: int, ativo: bool}|null */
    public function porUuid(string $uuid): ?array;

    /**
     * @param  list<int>  $ids
     * @return list<int> só os que estão ativos
     */
    public function ativos(array $ids): array;

    /**
     * Identificador público de cada acesso da organização em contexto, para respostas (ex.: ator da
     * auditoria). Acesso revogado continua com o mesmo uuid.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function uuids(array $ids): array;

    /**
     * Pessoa ligada a cada acesso da organização em contexto (ex.: autor na linha do tempo). Acesso
     * revogado continua apontando para a mesma Pessoa.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    public function pessoas(array $ids): array;

    /**
     * Conta de cada acesso ativo (ex.: destinatário de aviso de serviço). Revogado fica de fora.
     *
     * @param  list<int>  $ids
     * @return array<int, int> acesso → conta
     */
    public function contasAtivas(array $ids): array;

    /** Acesso ativo ligado à Pessoa (uma Pessoa por conta, BR-002), ou null. */
    public function ativoDaPessoa(int $pessoaId): ?int;

    /**
     * Liga o acesso a outra Pessoa (mesclagem: a mantida herda o acesso da absorvida, e desfazer
     * devolve). Só muda `pessoa_id`; a conta e as atribuições continuam as mesmas.
     */
    public function religarPessoa(int $acessoId, int $pessoaId): void;

    /** Revoga o acesso ativo da Pessoa (removedor LGPD, OIK-ADR-0005 §8). @return int|null o acesso revogado */
    public function revogarDaPessoa(int $pessoaId): ?int;

    /** A Pessoa já teve acesso (ativo ou revogado) nesta organização. */
    public function pessoaTeveAcesso(int $pessoaId): bool;
}
