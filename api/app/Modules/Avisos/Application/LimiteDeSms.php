<?php

namespace App\Modules\Avisos\Application;

use Illuminate\Cache\RateLimiter;

/** Limite de SMS por número, contra fraude de SMS (OIK-ADR-0008 §11). A chave leva o número em HMAC. */
final class LimiteDeSms
{
    private const UMA_HORA = 3600;

    private const UM_DIA = 86400;

    public function __construct(private readonly RateLimiter $limitador) {}

    /** Conta um envio para o número, se ainda couber nos dois limites. */
    public function reservar(string $numero): bool
    {
        $resumo = hash_hmac('sha256', $numero, (string) config('oikora.chave_resumo'));
        $porHora = 'avisos:sms:hora:'.$resumo;
        $porDia = 'avisos:sms:dia:'.$resumo;

        if ($this->limitador->tooManyAttempts($porHora, (int) config('oikora.codigos.sms_por_hora'))
            || $this->limitador->tooManyAttempts($porDia, (int) config('oikora.codigos.sms_por_dia'))) {
            return false;
        }

        $this->limitador->hit($porHora, self::UMA_HORA);
        $this->limitador->hit($porDia, self::UM_DIA);

        return true;
    }
}
