<?php

use App\Modules\Identidade\Presentation\ConvitesController;
use Illuminate\Support\Facades\Route;

Route::get('acessos', [ConvitesController::class, 'acessos']);
Route::post('convites', [ConvitesController::class, 'convidar']);
Route::delete('convites/{convite}', [ConvitesController::class, 'revogarConvite'])->whereUuid('convite');
Route::delete('acessos/{acesso}', [ConvitesController::class, 'revogarAcesso'])->whereUuid('acesso');
