<?php

namespace App\Modules\Avisos\Application;

/**
 * Número ativo da organização (`whatsapp_numeros`, ligado pela T-21). Sem número ativo, convites e avisos
 * saem só por e-mail: o número da plataforma é só para códigos (OIK-ADR-0008 §10, §12).
 */
interface NumerosWhatsApp
{
    public function daOrganizacao(int $organizacaoId): ?NumeroWhatsApp;
}
