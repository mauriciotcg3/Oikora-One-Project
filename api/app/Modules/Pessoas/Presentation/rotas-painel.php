<?php

use App\Modules\Pessoas\Presentation\PessoasController;
use Illuminate\Support\Facades\Route;

Route::get('pessoas/{pessoa}', [PessoasController::class, 'mostrar'])->whereUuid('pessoa');
Route::get('busca', [PessoasController::class, 'buscar']);
