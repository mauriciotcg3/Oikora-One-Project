<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Pessoas\Application\Configuracao\Resultado;

/** Registra a conversão e mantém a pessoa entre os novos convertidos até batismo ou mudança de vínculo (RN-18). */
final readonly class RegistrarConversao
{
    public function __construct(private EditarFichaAction $editar) {}

    public function executar(string $pessoa, string $data, ?string $canal): Resultado
    {
        return $this->editar->executar($pessoa, 'eclesiasticos', [
            'aceitou_jesus' => true,
            'conversao_data' => $data,
            'conversao_canal' => $canal,
            'novo_convertido' => true,
        ]);
    }
}
