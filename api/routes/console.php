<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rotinas do agendador (OIK-ADR-0008 §6): uma instância, sem sobreposição (trava no cache do banco).
Schedule::command('envios:varrer')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('avisos:criar-particoes')->monthlyOn(20, '03:00')->onOneServer();
