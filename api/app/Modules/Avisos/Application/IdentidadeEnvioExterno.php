<?php

namespace App\Modules\Avisos\Application;

final readonly class IdentidadeEnvioExterno
{
    public function __construct(
        public int $id,
        public int $organizacaoId,
        public int $avisoId,
        public string $avisoCriadoEm,
    ) {}
}
