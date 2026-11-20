<?php

namespace App\Modules\Pessoas\Presentation\Configuracao;

use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Domain\ListaValores;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regra para os cadastros novos (T-26 em diante): o uuid precisa ser de um item **ativo** da lista
 * (CA-30). Item desativado continua mostrado onde já foi usado (`ListasDeValores::nomes`).
 */
final readonly class ValorAtivoDaLista implements ValidationRule
{
    public function __construct(private ListaValores $lista) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || app(ListasDeValores::class)->idAtivo($this->lista, $value) === null) {
            $fail('O item escolhido não existe ou foi desativado.');
        }
    }
}
