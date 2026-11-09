<?php

namespace App\Modules\Autorizacao\Domain;

enum EscopoTipo: string
{
    case Organizacao = 'organizacao';
    case Regional = 'regional';
    case Igreja = 'igreja';
}
