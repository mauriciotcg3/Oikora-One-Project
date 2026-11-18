<?php

use App\Modules\Avisos\Presentation\DescadastroController;
use Illuminate\Support\Facades\Route;

/** Rotas sem sessão do módulo Avisos: só o descadastro por link assinado (RFC 8058). */
Route::post('descadastro', DescadastroController::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->name('avisos.descadastro');
