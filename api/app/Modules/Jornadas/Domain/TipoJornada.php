<?php

namespace App\Modules\Jornadas\Domain;

enum TipoJornada: string
{
    case ConectarWhatsapp = 'conectar_whatsapp';

    /** @return list<string> */
    public function camposPublicos(): array
    {
        return ['nome_exibicao', 'business_account_id'];
    }

    /** @return list<string> */
    public function camposSensiveis(): array
    {
        return ['numero_telefone'];
    }
}
