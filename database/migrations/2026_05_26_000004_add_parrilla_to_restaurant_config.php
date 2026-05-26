<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->unsignedSmallInteger('parrilla_capacidad')->default(14)->after('terraza_capacidad');
            $table->boolean('parrilla_cerrado')->default(false)->after('terraza_cerrado');
        });

        // Actualizar capacidades a los límites exactos por sector (= 70% de aforo real)
        // y ajustar capacidad_pct=100 y sector_alerta_pct=100 para que el sistema
        // use estos valores como límites directos.
        DB::table('restaurant_config')->update([
            'salon_capacidad'    => 40,
            'galeria_capacidad'  => 50,
            'terraza_capacidad'  => 55,
            'parrilla_capacidad' => 14,
            'capacidad_pct'      => 100,
            'sector_alerta_pct'  => 100,
        ]);
    }

    public function down(): void
    {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->dropColumn(['parrilla_capacidad', 'parrilla_cerrado']);
        });

        DB::table('restaurant_config')->update([
            'salon_capacidad'   => 50,
            'galeria_capacidad' => 60,
            'terraza_capacidad' => 60,
            'capacidad_pct'     => 70,
            'sector_alerta_pct' => 70,
        ]);
    }
};
