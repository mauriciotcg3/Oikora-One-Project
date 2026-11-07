<?php

namespace App\Modules\Identidade\Application;

final readonly class CodigoGuardado
{
    public function __construct(
        public int $id,
        public int $contaId,
        public string $finalidade,
        public string $hash,
        public string $canal,
        public int $tentativas,
        public ?string $cifrado,
        public ?string $identificadorResumo,
    ) {}
}
