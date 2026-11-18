<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\NumerosWhatsApp;
use App\Modules\Avisos\Application\NumeroWhatsApp;

/**
 * Até a T-21 ligar `whatsapp_numeros` (Embedded Signup), nenhuma organização tem número ativo: convites e
 * avisos de serviço saem por e-mail, sem usar o número da plataforma.
 */
final class NumerosWhatsAppIndisponiveis implements NumerosWhatsApp
{
    public function daOrganizacao(int $organizacaoId): ?NumeroWhatsApp
    {
        return null;
    }
}
