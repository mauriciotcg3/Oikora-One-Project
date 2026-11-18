<?php

namespace App\Modules\Avisos\Application;

/** Número de WhatsApp ativo da organização na Cloud API da Meta (OIK-ADR-0008 §10). */
final readonly class NumeroWhatsApp
{
    public function __construct(
        public string $phoneNumberId,
        #[\SensitiveParameter] public string $token,
    ) {}
}
