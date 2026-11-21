<?php

use App\Modules\Avisos\Presentation\DescadastroController;
use App\Modules\Avisos\Presentation\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/meta', [WebhookController::class, 'confirmarMeta'])
    ->middleware('throttle:120,1');
Route::post('/webhooks/{provedor}', [WebhookController::class, 'receber'])
    ->whereIn('provedor', ['meta', 'postmark', 'twilio'])
    ->middleware('throttle:120,1');

/** Rotas sem sessão do módulo Avisos: só o descadastro por link assinado (RFC 8058). */
Route::post('descadastro', DescadastroController::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->name('avisos.descadastro');
