<?php

namespace App\Modules\Pessoas\Domain;

use DomainException;

/** Transição fora de Visitante → Frequentador → Membro (RN-07; CA-06). A mensagem diz o próximo passo. */
final class TransicaoVinculoInvalida extends DomainException
{
    public function __construct(string $mensagem, public readonly ?Vinculo $proximo)
    {
        parent::__construct($mensagem);
    }
}
