<?php

namespace Database\Seeders;

use App\Models\CostoEvento;
use Illuminate\Database\Seeder;

class CostosEventosSeeder extends Seeder
{
    public function run(): void
    {
        $costos = [
            // Menú niños (precio por niño)
            ['concepto' => 'menu_ninos', 'descripcion' => 'Menú niños por chico', 'precio' => 5000],
            // Infraestructura
            ['concepto' => 'cancha', 'descripcion' => 'Cancha por unidad', 'precio' => 15000],
            ['concepto' => 'coordinador', 'descripcion' => 'Coordinador por unidad', 'precio' => 8000],
            // Adultos
            ['concepto' => 'menu_adulto', 'descripcion' => 'Menú fijo adulto por persona', 'precio' => 6000],
            // Adicionales
            ['concepto' => 'adicional_papas', 'descripcion' => 'Bandeja de Papas Fritas Calientes', 'precio' => 4000],
            ['concepto' => 'adicional_sandwiches', 'descripcion' => 'Bandeja de Sándwiches de Miga', 'precio' => 3500],
            ['concepto' => 'adicional_frutas', 'descripcion' => 'Bandeja de Frutas', 'precio' => 3000],
            ['concepto' => 'adicional_helados', 'descripcion' => 'Helados (por unidad)', 'precio' => 1500],
        ];

        foreach ($costos as $costo) {
            CostoEvento::updateOrCreate(['concepto' => $costo['concepto']], $costo);
        }
    }
}
