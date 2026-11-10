<?php

use App\Modules\Autorizacao\Presentation\PapeisController;
use Illuminate\Support\Facades\Route;

Route::get('permissoes', [PapeisController::class, 'permissoes']);
Route::get('papeis', [PapeisController::class, 'listar']);
Route::post('papeis', [PapeisController::class, 'criar']);
Route::put('papeis/{papel}', [PapeisController::class, 'editar'])->whereUuid('papel');
Route::post('acessos/{acesso}/atribuicoes', [PapeisController::class, 'atribuir'])->whereUuid('acesso');
Route::delete('acessos/{acesso}/atribuicoes/{atribuicao}', [PapeisController::class, 'removerAtribuicao'])
    ->whereUuid(['acesso', 'atribuicao']);
