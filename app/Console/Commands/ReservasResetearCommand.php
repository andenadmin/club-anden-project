<?php

namespace App\Console\Commands;

use App\Models\RestaurantCapacityOverride;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReservasResetearCommand extends Command
{
    protected $signature = 'reservas:resetear {fecha? : Fecha en formato DD/MM/AAAA (default: hoy)}';

    protected $description = 'Elimina el override de capacidad máxima para una fecha, volviendo al límite global.';

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

        $deleted = RestaurantCapacityOverride::whereDate('fecha', $carbon->toDateString())->delete();

        $fechaFmt = $carbon->translatedFormat('l j \d\e F \d\e Y');

        if ($deleted) {
            $this->info("✅ Override eliminado para el {$fechaFmt}. Se aplica nuevamente el límite global.");
        } else {
            $this->line("No había override activo para el {$fechaFmt}.");
        }

        return self::SUCCESS;
    }
}
