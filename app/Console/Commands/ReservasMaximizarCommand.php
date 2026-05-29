<?php

namespace App\Console\Commands;

use App\Models\RestaurantCapacityOverride;
use App\Models\RestaurantConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReservasMaximizarCommand extends Command
{
    protected $signature = 'reservas:maximizar {fecha? : Fecha en formato DD/MM/AAAA (default: hoy)}';

    protected $description = 'Permite aceptar reservas hasta la capacidad máxima real para una fecha específica, sin límite de porcentaje.';

    public function handle(): int
    {
        $fechaArg = $this->argument('fecha');

        try {
            $carbon = $fechaArg
                ? Carbon::createFromFormat('d/m/Y', $fechaArg)
                : Carbon::today();
        } catch (\Throwable) {
            $this->error("Formato de fecha inválido. Usá DD/MM/AAAA (ej: 28/05/2026).");
            return self::FAILURE;
        }

        $config = RestaurantConfig::get();

        $data = [
            'salon_max'    => $config->salon_capacidad,
            'galeria_max'  => $config->galeria_capacidad,
            'terraza_max'  => $config->terraza_capacidad,
            'parrilla_max' => $config->parrilla_capacidad,
        ];

        RestaurantCapacityOverride::updateOrCreate(
            ['fecha' => $carbon->toDateString()],
            $data,
        );

        $fechaFmt = $carbon->translatedFormat('l j \d\e F \d\e Y');
        $this->info("✅ Capacidad maximizada para el {$fechaFmt}:");
        $this->table(
            ['Sector', 'Límite habitual (con pct)', 'Límite hoy (máximo)'],
            [
                ['Salón',    $config->limiteParaSector('salon'),    $data['salon_max']],
                ['Galería',  $config->limiteParaSector('galeria'),  $data['galeria_max']],
                ['Terraza',  $config->limiteParaSector('terraza'),  $data['terraza_max']],
                ['Parrilla', $config->limiteParaSector('parrilla'), $data['parrilla_max']],
            ]
        );
        $this->line("Para revertir: <comment>php artisan reservas:resetear" . ($fechaArg ? " {$fechaArg}" : '') . "</comment>");

        return self::SUCCESS;
    }
}
