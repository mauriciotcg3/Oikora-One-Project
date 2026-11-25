<?php

namespace App\Modules\Avisos\Application;

final readonly class CredenciaisNumeroWhatsApp
{
    public function __construct(
        public string $wabaId,
        public string $phoneNumberId,
        public string $nomeExibicao,
        #[\SensitiveParameter] public string $token,
    ) {}
}
