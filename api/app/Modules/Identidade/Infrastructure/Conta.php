<?php

namespace App\Modules\Identidade\Infrastructure;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Authenticatable as AutenticavelPadrao;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $email
 * @property string|null $celular
 * @property string $password
 * @property string $estado
 * @property string|null $dois_fatores_canal
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $email_verificado_em
 * @property CarbonImmutable|null $celular_verificado_em
 */
class Conta extends Model implements Authenticatable
{
    use AutenticavelPadrao;

    protected $table = 'contas';

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $casts = [
        'email_verificado_em' => 'immutable_datetime',
        'celular_verificado_em' => 'immutable_datetime',
    ];

    /** Sem "lembrar de mim": a sessão já dura 30 dias (OIK-ADR-0003 §9). */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
