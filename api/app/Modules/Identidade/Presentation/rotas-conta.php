<?php

use App\Modules\Identidade\Presentation\ContaController;
use App\Modules\Identidade\Presentation\ExigirSenhaConfirmada;
use App\Modules\Identidade\Presentation\VerificacaoContatoController;
use Illuminate\Support\Facades\Route;

Route::post('confirmar-senha', [ContaController::class, 'confirmarSenha']);
Route::get('2fa', [ContaController::class, 'estadoDoisFatores']);

Route::middleware(ExigirSenhaConfirmada::class)->group(function (): void {
    Route::post('2fa', [ContaController::class, 'ativarDoisFatores']);
    Route::delete('2fa', [ContaController::class, 'desativarDoisFatores']);
    Route::put('senha', [ContaController::class, 'trocarSenha']);
});

Route::post('verificacao/{tipo}', [VerificacaoContatoController::class, 'pedir'])->whereIn('tipo', ['email', 'celular']);
Route::post('verificacao/{tipo}/confirmar', [VerificacaoContatoController::class, 'confirmar'])->whereIn('tipo', ['email', 'celular']);
