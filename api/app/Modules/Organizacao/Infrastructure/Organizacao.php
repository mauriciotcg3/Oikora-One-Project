<?php

namespace App\Modules\Organizacao\Infrastructure;

use App\Modules\Organizacao\Domain\EstadoOrganizacao;
use App\Modules\Organizacao\Domain\ModoMatricula;
use Illuminate\Database\Eloquent\Model;

class Organizacao extends Model
{
    protected $table = 'organizacoes';

    protected $attributes = [
        'estado' => EstadoOrganizacao::Ativa->value,
        'fuso' => 'America/Sao_Paulo',
        'modo_matricula' => ModoMatricula::Manual->value,
    ];

    protected $casts = [
        'estado' => EstadoOrganizacao::class,
        'modo_matricula' => ModoMatricula::class,
        'limite_whatsapp_mensal' => 'integer',
    ];
}
