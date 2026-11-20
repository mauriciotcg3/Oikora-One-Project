<?php

namespace App\Modules\Avisos\Domain;

/** Os 10 estados de `envios`, fonte do estado da entrega (OIK-ADR-0008 §2). */
enum EstadoEnvio: string
{
    case Pendente = 'pendente';
    case Agendado = 'agendado';
    case Enviando = 'enviando';
    case Enviado = 'enviado';
    case Entregue = 'entregue';
    case Lido = 'lido';
    case FalhouTemporario = 'falhou_temporario';
    case FalhouDefinitivo = 'falhou_definitivo';
    case Suprimido = 'suprimido';
    case Cancelado = 'cancelado';

    /** Estados em que o job ainda pode chamar o provedor; do `enviado` em diante, nunca mais. */
    public function podeEnviar(): bool
    {
        return in_array($this, [self::Pendente, self::Agendado, self::FalhouTemporario, self::Enviando], true);
    }
}
