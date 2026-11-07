<?php

namespace App\Modules\Avisos\Contracts;

/** Entrega de códigos de identidade. O id aponta para `codigos_verificacao`, de Identidade. */
interface EnvioCodigo
{
    public function enviar(int $codigoId): void;
}
