<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Shared\Cifra\CampoSensivel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property string $nome
 * @property string $nome_busca
 * @property string|null $cpf
 * @property string|null $rg
 * @property string|null $rg_pais
 * @property string|null $necessidades_especiais
 * @property string|null $tipo_sanguineo
 * @property string|null $cpf_hash
 */
class Pessoa extends Model
{
    protected $table = 'pessoas';

    protected $guarded = [];

    protected $hidden = ['cpf', 'rg', 'rg_pais', 'necessidades_especiais', 'tipo_sanguineo', 'cpf_hash'];

    protected $casts = [
        'cpf' => CampoSensivel::class,
        'rg' => CampoSensivel::class,
        'rg_pais' => CampoSensivel::class,
        'necessidades_especiais' => CampoSensivel::class,
        'tipo_sanguineo' => CampoSensivel::class,
        'nascimento' => 'immutable_date',
        'data_casamento' => 'immutable_date',
        'conversao_data' => 'immutable_date',
        'batismo_data' => 'immutable_date',
        'entrada_em' => 'immutable_date',
        'excluida_em' => 'immutable_datetime',
        'aceitou_jesus' => 'boolean',
        'e_lider' => 'boolean',
        'e_pastor' => 'boolean',
        'novo_convertido' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Pessoa $pessoa): void {
            $pessoa->nome_busca = Str::lower(Str::ascii($pessoa->nome));

            $cpf = $pessoa->cpf;
            if ($cpf === null) {
                $pessoa->cpf_hash = null;

                return;
            }

            $chave = (string) config('oikora.cifra.cpf.chave_hmac');
            if ($chave === '') {
                throw new LogicException('OIKORA_CHAVE_HMAC_CPF não foi configurada.');
            }

            $pessoa->cpf_hash = hash_hmac('sha256', preg_replace('/\D+/', '', $cpf) ?? '', $chave);
        });
    }
}
