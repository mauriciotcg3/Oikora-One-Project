<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Identidade\Contracts\CodigosDeIdentidade;

/** Manda um código novo do desafio aberto, pelo canal escolhido. O anterior deixa de valer. */
final class ReenviarCodigo
{
    public function __construct(
        private readonly CodigosDeIdentidade $codigos,
        private readonly Contas $contas,
    ) {}

    /**
     * @return string|null destino mascarado; null quando a conta não tem contato nesse canal
     *
     * @throws FalhaDeDesafio
     */
    public function executar(int $contaId, string $canal): ?string
    {
        if ($this->contas->bloqueada($contaId)) {
            throw FalhaDeDesafio::contaBloqueada();
        }

        $destino = $this->contas->destinoDoCodigo($contaId, $canal);

        if ($destino === '') {
            return null;
        }

        $this->codigos->emitir($contaId, EmissaoDeCodigos::DOIS_FATORES, $canal);

        return $destino;
    }
}
