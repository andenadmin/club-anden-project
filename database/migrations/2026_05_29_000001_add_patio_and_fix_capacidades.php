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
            $table->unsignedSmallInteger('patio_capacidad')->default(79)->after('parrilla_capacidad');
            $table->boolean('patio_cerrado')->default(false)->after('parrilla_cerrado');
        });

        Schema::table('restaurant_capacity_overrides', function (Blueprint $table) {
            $table->unsignedSmallInteger('patio_max')->nullable()->after('parrilla_max');
        });

        // Corregir capacidades al aforo real de cada sector
        DB::table('restaurant_config')->update([
            'salon_capacidad'    => 52,
            'galeria_capacidad'  => 69,
            'terraza_capacidad'  => 20,
            'parrilla_capacidad' => 20,
            'patio_capacidad'    => 79,
            'capacidad_pct'      => 100,
            'sector_alerta_pct'  => 70,
        ]);
    }

    public function down(): void
    {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->dropColumn(['patio_capacidad', 'patio_cerrado']);
        });

        Schema::table('restaurant_capacity_overrides', function (Blueprint $table) {
            $table->dropColumn('patio_max');
        });

        DB::table('restaurant_config')->update([
            'salon_capacidad'    => 40,
            'galeria_capacidad'  => 50,
            'terraza_capacidad'  => 55,
            'parrilla_capacidad' => 14,
        ]);
    }
};
