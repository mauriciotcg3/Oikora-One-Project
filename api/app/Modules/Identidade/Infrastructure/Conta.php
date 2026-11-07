<?php

namespace App\Modules\Identidade\Infrastructure;

use Illuminate\Auth\Authenticatable as AutenticavelPadrao;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $email
 * @property string|null $celular
 * @property string $password
 */
class Conta extends Model implements Authenticatable
{
    use AutenticavelPadrao;

    protected $table = 'contas';

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Sem "lembrar de mim": a sessão já dura 30 dias (OIK-ADR-0003 §9). */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
