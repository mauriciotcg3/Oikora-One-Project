<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Identidade\Domain\CodigoDeRecuperacao;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Encryption\Encrypter;

/** Confere o segundo fator do desafio aberto no login, sem abrir sessão: isso cabe a quem recebeu a requisição. */
final class ConfirmarDoisFatores
{
    public function __construct(
        private readonly ConferirCodigo $conferir,
        private readonly Contas $contas,
        private readonly EventosAutenticacao $eventos,
        private readonly Totp $totp,
        private readonly Encrypter $cifra,
        private readonly RateLimiter $limitador,
    ) {}

    /** @throws FalhaDeDesafio */
    public function executar(int $contaId, ?string $codigo, ?string $codigoRecuperacao, string $identificadorResumo, string $ip): void
    {
        $ipResumo = Resumo::de($ip);

        if ($this->contas->bloqueada($contaId)) {
            $this->eventos->registrar('auth.login.conta_bloqueada', $contaId, $identificadorResumo, $ipResumo);

            throw FalhaDeDesafio::contaBloqueada();
        }

        try {
            $canal = $this->conferirFator($contaId, (string) $codigo, $codigoRecuperacao);
        } catch (FalhaDeDesafio $falha) {
            $evento = $falha->motivo === 'muitas_tentativas' ? 'auth.2fa.limite' : 'auth.2fa.falha';
            $this->eventos->registrar($evento, $contaId, $identificadorResumo, $ipResumo, $falha->canal);

            throw $falha;
        }

        $this->eventos->registrar('auth.2fa.sucesso', $contaId, $identificadorResumo, $ipResumo, $canal);
    }

    /**
     * @return string canal que completou o desafio
     *
     * @throws FalhaDeDesafio
     */
    private function conferirFator(int $contaId, string $codigo, ?string $codigoRecuperacao): string
    {
        if ($codigoRecuperacao !== null) {
            return $this->porRecuperacao($contaId, $codigoRecuperacao);
        }

        $fator = $this->contas->segundoFator($contaId);

        if ($fator?->canal === 'totp') {
            return $this->porTotp($contaId, (string) $fator->segredoCifrado, $codigo);
        }

        return $this->conferir->executar($contaId, EmissaoDeCodigos::DOIS_FATORES, $codigo)->canal;
    }

    /** @throws FalhaDeDesafio */
    private function porRecuperacao(int $contaId, string $codigo): string
    {
        return $this->comLimite($contaId, 'recuperacao', fn (): bool => $this->contas->consumirCodigoDeRecuperacao(
            $contaId,
            Resumo::de(CodigoDeRecuperacao::normalizar($codigo)),
        ));
    }

    /** @throws FalhaDeDesafio */
    private function porTotp(int $contaId, string $segredoCifrado, string $codigo): string
    {
        return $this->comLimite($contaId, 'totp', fn (): bool => $this->totp->conferir(
            $contaId,
            $this->cifra->decryptString($segredoCifrado),
            $codigo,
        ));
    }

    /**
     * TOTP e recuperação não têm linha em `codigos_verificacao`, então os erros contam aqui.
     *
     * @param  callable(): bool  $conferir
     *
     * @throws FalhaDeDesafio
     */
    private function comLimite(int $contaId, string $canal, callable $conferir): string
    {
        $chave = 'identidade:segundo-fator:erros:'.$contaId;

        if ($this->limitador->tooManyAttempts($chave, (int) config('oikora.codigos.tentativas'))) {
            throw FalhaDeDesafio::muitasTentativas($canal);
        }

        if (! $conferir()) {
            $this->limitador->hit($chave, (int) config('oikora.codigos.validade_minutos') * 60);

            throw FalhaDeDesafio::codigoIncorreto($canal);
        }

        $this->limitador->clear($chave);

        return $canal;
    }
}
