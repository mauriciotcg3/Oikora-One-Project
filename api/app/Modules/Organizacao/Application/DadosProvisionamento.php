<?php

namespace App\Modules\Organizacao\Application;

use App\Modules\Organizacao\Domain\Fuso;

final readonly class DadosProvisionamento
{
    public function __construct(
        public string $identificador,
        public string $nome,
        public string $igreja,
        public string $adminNome,
        public ?string $adminEmail,
        public ?string $adminCelular,
        public string $operador,
        public string $motivo,
        public bool $emMigracao = false,
        public string $fuso = Fuso::PADRAO,
    ) {}
}
