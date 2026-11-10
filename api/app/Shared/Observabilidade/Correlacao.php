<?php

namespace App\Shared\Observabilidade;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Correlação fora da requisição (OIK-ADR-0010 §9; COMM-005). Job despachado numa requisição herda o
 * `request_id` dela pelo Context e o usa como `correlation_id`; rotina agendada, ou job sem origem, gera o seu.
 */
final class Correlacao
{
    public static function novoTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** Chamado no início de cada job e de cada comando agendado. */
    public static function garantir(): void
    {
        $requestId = Context::get('request_id');

        if (! is_string($requestId) || $requestId === '') {
            $requestId = (string) Str::uuid();
            Context::add('request_id', $requestId);
        }

        if (! is_string(Context::get('correlation_id'))) {
            Context::add('correlation_id', $requestId);
        }

        if (! is_string(Context::get('trace_id'))) {
            Context::add('trace_id', self::novoTraceId());
        }
    }
}
