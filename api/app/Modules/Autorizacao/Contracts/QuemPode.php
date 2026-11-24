<?php

namespace App\Modules\Autorizacao\Contracts;

use App\Modules\Autorizacao\Domain\Permissao;

/**
 * Quem tem uma permissão numa igreja da organização em contexto (ex.: avisar as secretarias que
 * aprovam uma transferência, FLOW-009). Devolve acessos; Identidade diz quais estão ativos e de que conta.
 */
interface QuemPode
{
    /** @return list<int> ids de acesso */
    public function naIgreja(Permissao $permissao, int $igrejaId): array;
}
