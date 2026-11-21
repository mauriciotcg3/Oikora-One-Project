<?php

namespace App\Modules\Avisos\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Avisos\Application\ReceberWebhook;
use App\Modules\Avisos\Application\RequisicaoWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WebhookController extends Controller
{
    public function confirmarMeta(Request $request): Response
    {
        $modo = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $desafio = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $esperado = (string) config('oikora.webhooks.meta.verify_token');

        if ($modo !== 'subscribe' || $esperado === '' || ! hash_equals($esperado, $token)) {
            abort(401);
        }

        return response($desafio, 200)->header('Content-Type', 'text/plain');
    }

    public function receber(string $provedor, Request $request, ReceberWebhook $receber): Response
    {
        abort_unless(in_array($provedor, ['meta', 'postmark', 'twilio'], true), 404);

        $requisicao = new RequisicaoWebhook(
            $request->url(),
            [
                'authorization' => $request->header('Authorization'),
                'x-hub-signature-256' => $request->header('X-Hub-Signature-256'),
                'x-twilio-signature' => $request->header('X-Twilio-Signature'),
            ],
            $request->all(),
            $request->getContent(),
            $request->ip(),
        );

        if (! $receber->executar($provedor, $requisicao)) {
            abort(401);
        }

        return response('', 200);
    }
}
