<?php

use App\Modules\Avisos\Presentation\CaixaAvisosController;
use Illuminate\Support\Facades\Route;

/** Caixa de avisos da conta na organização ativa (T-20): prefixo `api/v1/avisos`. */
Route::get('/', [CaixaAvisosController::class, 'listar']);
Route::get('nao-lidos', [CaixaAvisosController::class, 'naoLidos']);
Route::post('lidos', [CaixaAvisosController::class, 'marcarTodos']);
Route::post('{aviso}/lido', [CaixaAvisosController::class, 'marcarLido'])->whereUuid('aviso');
