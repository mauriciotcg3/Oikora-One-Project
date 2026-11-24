<?php

namespace App\Modules\Pessoas\Contracts;

/**
 * Contatos da ficha para o módulo Avisos, sob a RLS da organização em contexto (OIK-ADR-0008 §13).
 * O endereço é lido na hora do envio; o descadastro fica gravado no próprio contato.
 */
interface ContatosDaPessoa
{
    /**
     * Endereço de entrega: e-mail (`email`) ou celular marcado como WhatsApp (`whatsapp`), preferindo o
     * principal. Com `$respeitarDescadastro`, contato descadastrado não conta.
     */
    public function endereco(int $pessoaId, string $canal, bool $respeitarDescadastro): ?string;

    /** Identificador público da Pessoa, para links assinados. */
    public function uuid(int $pessoaId): ?string;

    /** Descadastra os contatos do canal da Pessoa (link de e-mail). Devolve se algum mudou. */
    public function descadastrar(string $pessoaUuid, string $canal): bool;

    /** Descadastra o celular WhatsApp que mandou "SAIR". Devolve quantos contatos mudaram. */
    public function descadastrarWhatsApp(string $celularE164): int;

    /** Registra que o endereço do canal falhou definitivamente. */
    public function marcarFalhaDeEntrega(int $pessoaId, string $canal): bool;

    /**
     * Pessoa da organização para o convite ao painel (T-22), fora da lixeira, com o e-mail e o celular
     * principais. Lida como rotina de sistema: quem convida é governança, não precisa ver a ficha.
     *
     * @return array{id: int, uuid: string, nome: string, email: string|null, celular: string|null}|null
     */
    public function paraConvite(string $pessoaUuid): ?array;

    /** Nome da Pessoa, para os dados mínimos do convite. */
    public function nome(int $pessoaId): ?string;

    /**
     * Nomes de várias Pessoas da organização ativa, sem expor a ficha completa.
     *
     * @param  list<int>  $pessoaIds
     * @return array<int, string>
     */
    public function nomes(array $pessoaIds): array;
}
