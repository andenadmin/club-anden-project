<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('salon_capacidad')->default(50);
            $table->unsignedInteger('galeria_capacidad')->default(60);
            $table->unsignedInteger('terraza_capacidad')->default(60);
            $table->unsignedTinyInteger('capacidad_pct')->default(70);
            $table->timestamps();
        });

        // Fila única de configuración
        DB::table('restaurant_config')->insert([
            'salon_capacidad'   => 50,
            'galeria_capacidad' => 60,
            'terraza_capacidad' => 60,
            'capacidad_pct'     => 70,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_config');
    }
};
