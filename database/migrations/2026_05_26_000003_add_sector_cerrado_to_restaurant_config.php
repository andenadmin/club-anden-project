<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->boolean('salon_cerrado')->default(false)->after('sector_alerta_pct');
            $table->boolean('galeria_cerrado')->default(false)->after('salon_cerrado');
            $table->boolean('terraza_cerrado')->default(false)->after('galeria_cerrado');
            // Fecha en que se cerró el sector (para re-abrir al día siguiente)
            $table->date('sectores_cerrado_fecha')->nullable()->after('terraza_cerrado');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->dropColumn(['salon_cerrado', 'galeria_cerrado', 'terraza_cerrado', 'sectores_cerrado_fecha']);
        });
    }
};
