<?php

namespace App\Modules\Identidade\Contracts;

final readonly class CodigoParaEnvio
{
    public function __construct(
        public string $codigo,
        public string $destino,
        public string $canal,
        public string $finalidade,
    ) {}
}
