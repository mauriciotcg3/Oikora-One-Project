<?php

namespace App\Modules\Pessoas\Infrastructure;

use Illuminate\Database\Eloquent\Model;

class PessoaEndereco extends Model
{
    protected $table = 'pessoa_enderecos';

    protected $guarded = [];
}
