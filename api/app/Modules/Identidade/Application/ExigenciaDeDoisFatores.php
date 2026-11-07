<?php

namespace App\Modules\Identidade\Application;

/** Diz quem é obrigado a ter 2FA (RN-16). Quem responde de verdade é o papel Administrador, a partir da T-16. */
interface ExigenciaDeDoisFatores
{
    public function obrigatorio(int $contaId): bool;
}
