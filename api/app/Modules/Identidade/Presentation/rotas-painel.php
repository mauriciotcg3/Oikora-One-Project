<?php

use App\Modules\Identidade\Presentation\ConvitesController;
use Illuminate\Support\Facades\Route;

Route::post('convites', [ConvitesController::class, 'convidar']);
Route::delete('convites/{convite}', [ConvitesController::class, 'revogarConvite'])->whereUuid('convite');
Route::delete('acessos/{acesso}', [ConvitesController::class, 'revogarAcesso'])->whereUuid('acesso');
