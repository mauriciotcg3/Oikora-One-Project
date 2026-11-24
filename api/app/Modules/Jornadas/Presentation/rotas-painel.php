<?php

use App\Modules\Jornadas\Presentation\RascunhosController;
use Illuminate\Support\Facades\Route;

Route::get('rascunhos/{tipo}', [RascunhosController::class, 'ler']);
Route::put('rascunhos/{tipo}', [RascunhosController::class, 'salvar']);
Route::delete('rascunhos/{tipo}', [RascunhosController::class, 'descartar']);
