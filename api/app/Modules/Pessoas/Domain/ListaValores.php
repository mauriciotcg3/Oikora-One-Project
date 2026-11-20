<?php

namespace App\Modules\Pessoas\Domain;

/** Listas mantidas pelo administrador da organização (U-09; U-28: motivos em três listas). */
enum ListaValores: string
{
    case ComoChegou = 'como_chegou';
    case MotivoVinculo = 'motivo_vinculo';
    case MotivoInativacao = 'motivo_inativacao';
    case MotivoReativacao = 'motivo_reativacao';
    case TipoMembro = 'tipo_membro';
    case Funcao = 'funcao';
}
