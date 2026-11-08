<?php

namespace App\Modules\Pessoas\Infrastructure;

use Illuminate\Database\Eloquent\Model;

class PessoaContato extends Model
{
    protected $table = 'pessoa_contatos';

    protected $guarded = [];

    protected $casts = [
        'e_whatsapp' => 'boolean',
        'principal' => 'boolean',
        'entrega_falhou_em' => 'immutable_datetime',
        'descadastrado_em' => 'immutable_datetime',
    ];
}
