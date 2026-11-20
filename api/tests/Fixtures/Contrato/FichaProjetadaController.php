<?php

namespace Tests\Fixtures\Contrato;

/** Rota só de teste, registrada pelo `OpenApiTest` para exportar a fixture. */
final class FichaProjetadaController
{
    public function __invoke(): FichaProjetadaResource
    {
        return new FichaProjetadaResource(['uuid' => 'uuid-fixo', 'nome' => 'Nome', 'cpf' => '00000000000']);
    }
}
