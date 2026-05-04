<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sincroniza feriados el 1ro de cada mes: actualiza el año en curso
// y el siguiente (por si hay reservas a futuro en diciembre).
Schedule::command('feriados:sync', [now()->year])
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/feriados-sync.log'));

Schedule::command('feriados:sync', [now()->addYear()->year])
    ->monthlyOn(1, '03:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/feriados-sync.log'));

// §6.1.C — cap automático a las 12h sobre takeovers de asesor.
// Cada 10 min escanea sesiones con motivo_pausa = ASESOR_TAKEOVER y timestamp_pausa <= now-12h,
// las resetea a INICIO y manda MSG_TIMEOUT_ASESOR al usuario.
Schedule::command('inbox:sweep-takeovers')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/inbox-sweep.log'));
