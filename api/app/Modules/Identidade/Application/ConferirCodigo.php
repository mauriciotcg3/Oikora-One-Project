<?php

namespace App\Modules\Identidade\Application;

/** Confere o código aberto de uma finalidade e o consome. Serve ao 2FA e à verificação de contato. */
final class ConferirCodigo
{
    public function __construct(private readonly Codigos $codigos) {}

    /** @throws FalhaDeDesafio */
    public function executar(int $contaId, string $finalidade, string $codigo): CodigoGuardado
    {
        $guardado = $this->codigos->abertoDaConta($contaId, $finalidade);

        if ($guardado === null) {
            throw FalhaDeDesafio::codigoIncorreto();
        }

        if ($guardado->tentativas >= (int) config('oikora.codigos.tentativas')) {
            throw FalhaDeDesafio::muitasTentativas($guardado->canal);
        }

        if (! hash_equals($guardado->hash, Resumo::de($codigo)) || ! $this->codigos->consumir($guardado->id)) {
            $this->codigos->registrarErro($guardado->id);

            throw FalhaDeDesafio::codigoIncorreto($guardado->canal);
        }

        return $guardado;
    }
}
