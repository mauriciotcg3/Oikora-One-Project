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
}
