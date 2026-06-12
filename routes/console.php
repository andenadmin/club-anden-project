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

// §4 — Auto-confirmación de reservas de restaurante con fecha dentro de las próximas 24 hs.
// Corre a las 7, 11, 15, 19 y 23 hs todos los días.
foreach ([7, 11, 15, 19, 23] as $hora) {
    Schedule::command('reservas:auto-confirm-restaurante')
        ->dailyAt("{$hora}:00")
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/auto-confirm-restaurante.log'));
}

// Recordatorios de eventos: corre a las 10 hs todos los días.
// Busca reservas de EVENTOS en las próximas 48hs que no recibieron recordatorio aún.
Schedule::command('reservas:recordatorio-eventos', ['--horas=48'])
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/recordatorios-eventos.log'));

// §6.1.C — cap automático a las 12h sobre takeovers de asesor.
// Cada 10 min escanea sesiones con motivo_pausa = ASESOR_TAKEOVER y timestamp_pausa <= now-12h,
// las resetea a INICIO y manda MSG_TIMEOUT_ASESOR al usuario.
Schedule::command('inbox:sweep-takeovers')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/inbox-sweep.log'));
