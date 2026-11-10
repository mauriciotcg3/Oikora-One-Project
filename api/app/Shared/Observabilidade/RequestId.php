<?php

namespace App\Shared\Observabilidade;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlação da requisição (OIK-ADR-0010 §9): aceita o `X-Request-Id` da borda ou gera um UUID, lê o
 * `traceparent` W3C e o `X-Cliente`, guarda tudo no Context (que segue para os jobs, COMM-005) e devolve
 * o `request_id` no cabeçalho e no corpo de todo erro.
 */
class RequestId
{
    /** Valor aceito da borda: curto e sem nada que injete linha ou cabeçalho no log. */
    private const ID_SEGURO = '/^[A-Za-z0-9._:-]{8,128}$/';

    private const TRACEPARENT = '/^[0-9a-f]{2}-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/';

    private const CLIENTE = '/^(painel|membro|app)@[A-Za-z0-9.+_-]{1,40}$/';

    public function __construct(private readonly Rastreamento $rastreamento) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->requestId($request);
        Context::add('request_id', $id);
        Context::add('correlation_id', $id);

        $span = $this->rastreamento->iniciarRequisicao($request->method().' /'.$request->path(), array_filter([
            'traceparent' => (string) $request->header('traceparent', ''),
            'tracestate' => (string) $request->header('tracestate', ''),
        ]));
        Context::add('trace_id', $span?->getContext()->getTraceId() ?? $this->traceId($request));

        $cliente = (string) $request->header('X-Cliente', '');
        if (preg_match(self::CLIENTE, $cliente) === 1) {
            Context::add('cliente', $cliente);
        }

        try {
            /** @var Response $resposta */
            $resposta = $next($request);
        } finally {
            if ($span !== null) {
                $span->setAttribute('http.response.status_code', isset($resposta) ? $resposta->getStatusCode() : 500);
                $span->setAttribute('oikora.request_id', $id);
                $span->end();
            }
        }

        $resposta->headers->set('X-Request-Id', $id);

        if ($resposta instanceof JsonResponse && $resposta->getStatusCode() >= 400) {
            $resposta->setData([...(array) $resposta->getData(true), 'request_id' => $id]);
        }

        return $resposta;
    }

    private function requestId(Request $request): string
    {
        $recebido = (string) $request->header('X-Request-Id', '');

        return preg_match(self::ID_SEGURO, $recebido) === 1 ? $recebido : (string) Str::uuid();
    }

    private function traceId(Request $request): string
    {
        $traceparent = strtolower((string) $request->header('traceparent', ''));

        if (preg_match(self::TRACEPARENT, $traceparent, $partes) === 1 && $partes[1] !== str_repeat('0', 32)) {
            return $partes[1];
        }

        return Correlacao::novoTraceId();
    }
}
