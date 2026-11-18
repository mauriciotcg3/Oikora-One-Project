<?php

use App\Modules\Auditoria\Presentation\AuditoriaController;
use Illuminate\Support\Facades\Route;

Route::get('auditoria', [AuditoriaController::class, 'listar']);
