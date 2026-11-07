<?php

namespace App\Modules\Identidade\Application;

interface Contas
{
    public function localizar(string $identificador): ?ContaLocalizada;

    /** Destino mascarado do código; vazio quando o canal não envia mensagem (TOTP). */
    public function destinoDoCodigo(int $contaId, string $canal): string;

    /** Destino em claro, só para a entrega do código; vazio quando a conta não tem esse contato. */
    public function destinoDeEnvio(int $contaId, string $canal): string;

    public function bloqueada(int $contaId): bool;

    /** Canal da recuperação quando o identificador pertence à conta e está verificado; senão, null. */
    public function canalVerificado(int $contaId, string $identificador): ?string;

    /** Troca a senha e encerra todas as sessões da conta, na mesma transação. */
    public function redefinirSenha(int $contaId, string $hashSenha): void;
}
