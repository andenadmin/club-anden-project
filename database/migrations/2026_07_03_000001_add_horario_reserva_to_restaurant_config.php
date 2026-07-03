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
            $table->unsignedTinyInteger('reserva_hora_desde')->default(9)->after('sector_alerta_pct');
            $table->unsignedTinyInteger('reserva_hora_hasta')->default(22)->after('reserva_hora_desde');
        });

        DB::table('restaurant_config')->update([
            'reserva_hora_desde' => 9,
            'reserva_hora_hasta' => 22,
        ]);
    }

    public function down(): void
    {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->dropColumn(['reserva_hora_desde', 'reserva_hora_hasta']);
        });
    }
};
