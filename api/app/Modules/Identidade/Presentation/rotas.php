<?php

use App\Modules\Identidade\Presentation\DoisFatoresController;
use App\Modules\Identidade\Presentation\RecuperacaoSenhaController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::post('login', [AuthenticatedSessionController::class, 'store']);
Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:web');
Route::post('2fa/confirmar', [DoisFatoresController::class, 'confirmar']);
Route::post('2fa/enviar', [DoisFatoresController::class, 'enviar']);
Route::post('senha/esqueci', [RecuperacaoSenhaController::class, 'esqueci']);
Route::post('senha/redefinir', [RecuperacaoSenhaController::class, 'redefinir']);
