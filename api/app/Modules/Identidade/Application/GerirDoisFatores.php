<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Identidade\Domain\CodigoDeRecuperacao;
use Illuminate\Contracts\Encryption\Encrypter;

/** Ativar, consultar e desativar o 2FA da própria conta (OIK-ADR-0003 §7). */
final class GerirDoisFatores
{
    public function __construct(
        private readonly Contas $contas,
        private readonly EventosAutenticacao $eventos,
        private readonly ExigenciaDeDoisFatores $exigencia,
        private readonly Totp $totp,
        private readonly Encrypter $cifra,
    ) {}

    /** @return array{canal: string|null, recuperacao_restantes: int} */
    public function estado(int $contaId): array
    {
        $fator = $this->contas->segundoFator($contaId);

        return ['canal' => $fator?->canal, 'recuperacao_restantes' => count($fator->recuperacao ?? [])];
    }

    /** @return list<string>|null códigos de recuperação; null quando o contato não está verificado */
    public function ativarPorMensagem(int $contaId, string $canal, string $ip): ?array
    {
        if (! $this->contas->contatoVerificado($contaId, $canal)) {
            return null;
        }

        return $this->ativar($contaId, $canal, $ip);
    }

    /** @return array{segredo: string, otpauth_url: string}|null null quando a conta já usa TOTP */
    public function iniciarTotp(int $contaId): ?array
    {
        if ($this->contas->segundoFator($contaId)?->canal === 'totp') {
            return null;
        }

        $segredo = $this->totp->novoSegredo();
        $this->contas->guardarSegredoTotp($contaId, $this->cifra->encryptString($segredo));

        $rotulo = $this->contas->destinoDeEnvio($contaId, 'email') ?: $this->contas->destinoDeEnvio($contaId, 'whatsapp');

        return ['segredo' => $segredo, 'otpauth_url' => $this->totp->urlDeProvisionamento($rotulo, $segredo)];
    }

    /** @return list<string>|null códigos de recuperação; null quando o código do aplicativo não confere */
    public function concluirTotp(int $contaId, string $codigo, string $ip): ?array
    {
        $cifrado = $this->contas->segundoFator($contaId)?->segredoCifrado;

        if ($cifrado === null || ! $this->totp->conferir($contaId, $this->cifra->decryptString($cifrado), $codigo)) {
            return null;
        }

        return $this->ativar($contaId, 'totp', $ip);
    }

    /** @return bool false quando a conta é obrigada a ter 2FA */
    public function desativar(int $contaId, string $ip): bool
    {
        if ($this->exigencia->obrigatorio($contaId)) {
            return false;
        }

        $this->contas->desativarDoisFatores($contaId);
        $this->eventos->registrar('auth.2fa.desativado', $contaId, Resumo::de((string) $contaId), Resumo::de($ip));

        return true;
    }

    /** @return list<string> */
    private function ativar(int $contaId, string $canal, string $ip): array
    {
        $codigos = CodigoDeRecuperacao::gerarLote();
        $resumos = array_map(fn (string $codigo): string => Resumo::de(CodigoDeRecuperacao::normalizar($codigo)), $codigos);

        $this->contas->ativarDoisFatores($contaId, $canal, $resumos);
        $this->eventos->registrar('auth.2fa.ativado', $contaId, Resumo::de((string) $contaId), Resumo::de($ip), $canal);

        return $codigos;
    }
}
