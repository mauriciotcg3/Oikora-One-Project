<?php

namespace App\Modules\Identidade\Application;

/** Confere o código do desafio aberto no login, sem abrir sessão: isso cabe a quem recebeu a requisição. */
final class ConfirmarDoisFatores
{
    public function __construct(
        private readonly Codigos $codigos,
        private readonly Contas $contas,
        private readonly EventosAutenticacao $eventos,
    ) {}

    /** @throws FalhaDeDesafio */
    public function executar(int $contaId, string $codigo, string $identificadorResumo, string $ip): void
    {
        $ipResumo = Resumo::de($ip);

        if ($this->contas->bloqueada($contaId)) {
            $this->eventos->registrar('auth.login.conta_bloqueada', $contaId, $identificadorResumo, $ipResumo);

            throw FalhaDeDesafio::contaBloqueada();
        }

        $guardado = $this->codigos->abertoDaConta($contaId, EmissaoDeCodigos::DOIS_FATORES);

        if ($guardado === null) {
            $this->eventos->registrar('auth.2fa.falha', $contaId, $identificadorResumo, $ipResumo);

            throw FalhaDeDesafio::codigoIncorreto();
        }

        if ($guardado->tentativas >= (int) config('oikora.codigos.tentativas')) {
            $this->eventos->registrar('auth.2fa.limite', $contaId, $identificadorResumo, $ipResumo, $guardado->canal);

            throw FalhaDeDesafio::muitasTentativas();
        }

        if (! hash_equals($guardado->hash, Resumo::de($codigo)) || ! $this->codigos->consumir($guardado->id)) {
            $this->codigos->registrarErro($guardado->id);
            $this->eventos->registrar('auth.2fa.falha', $contaId, $identificadorResumo, $ipResumo, $guardado->canal);

            throw FalhaDeDesafio::codigoIncorreto();
        }

        $this->eventos->registrar('auth.2fa.sucesso', $contaId, $identificadorResumo, $ipResumo, $guardado->canal);
    }
}
