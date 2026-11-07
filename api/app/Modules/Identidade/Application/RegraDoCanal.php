<?php

namespace App\Modules\Identidade\Application;

/**
 * Quem tem 2FA obrigatório não recebe o código pelo canal usado numa recuperação de senha
 * nas últimas 24h (OIK-ADR-0003 §7): quem tomou esse canal não pode usá-lo para os dois fatores.
 */
final class RegraDoCanal
{
    public function __construct(
        private readonly ExigenciaDeDoisFatores $exigencia,
        private readonly EventosAutenticacao $eventos,
        private readonly Contas $contas,
    ) {}

    /** @return string|null o canal que pode ser usado; null quando não sobra nenhum */
    public function escolher(int $contaId, string $preferido): ?string
    {
        if ($preferido === 'totp' || $this->canalBloqueado($contaId) !== $preferido) {
            return $preferido;
        }

        $outro = $preferido === 'email' ? 'whatsapp' : 'email';

        return $this->contas->destinoDeEnvio($contaId, $outro) === '' ? null : $outro;
    }

    private function canalBloqueado(int $contaId): ?string
    {
        if (! $this->exigencia->obrigatorio($contaId)) {
            return null;
        }

        $horas = (int) config('oikora.dois_fatores.canal_bloqueado_horas');
        $canal = $this->eventos->canalDaUltimaRedefinicao($contaId, now()->subHours($horas));

        // SMS é a reserva do WhatsApp: os dois chegam no mesmo número.
        return $canal === 'sms' ? 'whatsapp' : $canal;
    }
}
