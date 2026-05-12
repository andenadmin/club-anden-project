<?php

namespace Database\Seeders;

use App\Models\Feriado;
use Illuminate\Database\Seeder;

class FeriadosSeeder extends Seeder
{
    public function run(): void
    {
        $feriados = [
            // 2025
            ['fecha' => '2025-01-01', 'nombre' => 'Año Nuevo'],
            ['fecha' => '2025-03-03', 'nombre' => 'Carnaval'],
            ['fecha' => '2025-03-04', 'nombre' => 'Carnaval'],
            ['fecha' => '2025-03-24', 'nombre' => 'Día Nacional de la Memoria por la Verdad y la Justicia'],
            ['fecha' => '2025-04-02', 'nombre' => 'Día del Veterano y de los Caídos en la Guerra de Malvinas'],
            ['fecha' => '2025-04-18', 'nombre' => 'Viernes Santo'],
            ['fecha' => '2025-05-01', 'nombre' => 'Día del Trabajador'],
            ['fecha' => '2025-05-25', 'nombre' => 'Día de la Revolución de Mayo'],
            ['fecha' => '2025-06-20', 'nombre' => 'Paso a la Inmortalidad del Gral. Manuel Belgrano'],
            ['fecha' => '2025-07-09', 'nombre' => 'Día de la Independencia'],
            ['fecha' => '2025-08-18', 'nombre' => 'Paso a la Inmortalidad del Gral. José de San Martín (trasladado)'],
            ['fecha' => '2025-10-13', 'nombre' => 'Día del Respeto a la Diversidad Cultural (trasladado)'],
            ['fecha' => '2025-11-20', 'nombre' => 'Día de la Soberanía Nacional'],
            ['fecha' => '2025-12-08', 'nombre' => 'Inmaculada Concepción de María'],
            ['fecha' => '2025-12-25', 'nombre' => 'Navidad'],
            // 2026
            ['fecha' => '2026-01-01', 'nombre' => 'Año Nuevo'],
            ['fecha' => '2026-02-16', 'nombre' => 'Carnaval'],
            ['fecha' => '2026-02-17', 'nombre' => 'Carnaval'],
            ['fecha' => '2026-03-24', 'nombre' => 'Día Nacional de la Memoria por la Verdad y la Justicia'],
            ['fecha' => '2026-04-02', 'nombre' => 'Día del Veterano y de los Caídos en la Guerra de Malvinas'],
            ['fecha' => '2026-04-03', 'nombre' => 'Viernes Santo'],
            ['fecha' => '2026-05-01', 'nombre' => 'Día del Trabajador'],
            ['fecha' => '2026-05-25', 'nombre' => 'Día de la Revolución de Mayo'],
            ['fecha' => '2026-06-20', 'nombre' => 'Paso a la Inmortalidad del Gral. Manuel Belgrano'],
            ['fecha' => '2026-07-09', 'nombre' => 'Día de la Independencia'],
            ['fecha' => '2026-08-17', 'nombre' => 'Paso a la Inmortalidad del Gral. José de San Martín'],
            ['fecha' => '2026-10-12', 'nombre' => 'Día del Respeto a la Diversidad Cultural'],
            ['fecha' => '2026-11-20', 'nombre' => 'Día de la Soberanía Nacional'],
            ['fecha' => '2026-12-08', 'nombre' => 'Inmaculada Concepción de María'],
            ['fecha' => '2026-12-25', 'nombre' => 'Navidad'],
        ];

        Feriado::upsert($feriados, uniqueBy: ['fecha'], update: ['nombre', 'updated_at']);
    }
}
