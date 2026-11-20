<?php

use App\Modules\Organizacao\Presentation\IgrejasController;
use App\Modules\Organizacao\Presentation\OrganizacaoController;
use Illuminate\Support\Facades\Route;

Route::get('organizacao', [OrganizacaoController::class, 'mostrar']);
Route::put('organizacao', [OrganizacaoController::class, 'atualizar']);
Route::post('organizacao/logo', [OrganizacaoController::class, 'logo']);

// Igreja não se exclui (P FR-001): não há rota DELETE.
Route::get('igrejas', [IgrejasController::class, 'listar']);
Route::post('igrejas', [IgrejasController::class, 'criar']);
Route::put('igrejas/{igreja}', [IgrejasController::class, 'editar'])->whereUuid('igreja');
