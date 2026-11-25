<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Pessoas\Application\Configuracao\Resultado;

/** Registra o batismo e encerra a permanência na lista de novos convertidos sem apagar a conversão (RN-18). */
final readonly class RegistrarBatismo
{
    public function __construct(private EditarFichaAction $editar) {}

    public function executar(string $pessoa, string $data, ?string $batizadoPor): Resultado
    {
        return $this->editar->executar($pessoa, 'eclesiasticos', [
            'batismo_data' => $data,
            'batizado_por' => $batizadoPor,
            'novo_convertido' => false,
        ]);
    }
}
