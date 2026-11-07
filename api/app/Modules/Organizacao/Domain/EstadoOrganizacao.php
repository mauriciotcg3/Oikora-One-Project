<?php

namespace App\Modules\Organizacao\Domain;

enum EstadoOrganizacao: string
{
    case Ativa = 'ativa';
    case EmMigracao = 'em_migracao';
}
