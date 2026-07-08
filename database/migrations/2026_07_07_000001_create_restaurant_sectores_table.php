<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_sectores', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // fijo, nunca editable — ata con RestaurantConfig (salon_capacidad, salon_cerrado, etc.)
            $table->string('label'); // editable por el admin — lo que ve el cliente
            $table->unsignedInteger('orden');
            $table->boolean('activo')->default(true);
            $table->boolean('requiere_capacidad')->default(true); // false para "Sin preferencia": no chequea cupo
            $table->timestamps();
        });

        $now = now();
        $sectores = [
            ['key' => 'salon',    'label' => 'Salón',    'orden' => 1, 'requiere_capacidad' => true],
            ['key' => 'galeria',  'label' => 'Galería',  'orden' => 2, 'requiere_capacidad' => true],
            ['key' => 'terraza',  'label' => 'Terraza',  'orden' => 3, 'requiere_capacidad' => true],
            ['key' => 'parrilla', 'label' => 'Parrilla', 'orden' => 4, 'requiere_capacidad' => true],
            ['key' => 'patio',    'label' => 'Patio',    'orden' => 5, 'requiere_capacidad' => true],
            ['key' => 'sin_preferencia', 'label' => 'Sin preferencia', 'orden' => 6, 'requiere_capacidad' => false],
        ];

        foreach ($sectores as $s) {
            DB::table('restaurant_sectores')->insert(array_merge($s, [
                'activo'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_sectores');
    }
};
