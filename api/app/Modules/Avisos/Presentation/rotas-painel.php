<?php

use App\Modules\Avisos\Presentation\WhatsappNumeroController;
use Illuminate\Support\Facades\Route;

Route::get('avisos/whatsapp/oauth', [WhatsappNumeroController::class, 'autorizar']);
Route::get('avisos/whatsapp/oauth/retorno', [WhatsappNumeroController::class, 'retorno']);
Route::delete('avisos/whatsapp', [WhatsappNumeroController::class, 'desligar']);
