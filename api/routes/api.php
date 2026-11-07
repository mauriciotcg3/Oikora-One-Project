<?php

use App\Shared\Observabilidade\SaudeController;
use Illuminate\Support\Facades\Route;

Route::get('saude', SaudeController::class);
