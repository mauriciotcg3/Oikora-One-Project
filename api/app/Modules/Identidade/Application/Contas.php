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

    public function contatoVerificado(int $contaId, string $canal): bool;

    public function marcarVerificado(int $contaId, string $canal): void;

    public function hashDaSenha(int $contaId): string;

    public function segundoFator(int $contaId): ?SegundoFator;

    /** Guarda o segredo ainda sem ativar: o TOTP só vale depois de conferir um código do aplicativo. */
    public function guardarSegredoTotp(int $contaId, string $segredoCifrado): void;

    /** @param list<string> $resumosDeRecuperacao */
    public function ativarDoisFatores(int $contaId, string $canal, array $resumosDeRecuperacao): void;

    public function desativarDoisFatores(int $contaId): void;

    /** Devolve false quando o código não existe ou já foi usado. */
    public function consumirCodigoDeRecuperacao(int $contaId, string $resumo): bool;

    /** Troca a senha e encerra todas as sessões da conta, na mesma transação. */
    public function redefinirSenha(int $contaId, string $hashSenha): void;
}
