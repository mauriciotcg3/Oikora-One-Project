<?php

namespace App\Shared\Observabilidade;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Identifica a requisição e devolve o `request_id` em todo erro (OIK-ADR-0010 §9). A T-06 completa o log e o OTel. */
class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        Context::add('request_id', $id);

        /** @var Response $resposta */
        $resposta = $next($request);
        $resposta->headers->set('X-Request-Id', $id);

        if ($resposta instanceof JsonResponse && $resposta->getStatusCode() >= 400) {
            $resposta->setData([...(array) $resposta->getData(true), 'request_id' => $id]);
        }

        return $resposta;
    }
}
