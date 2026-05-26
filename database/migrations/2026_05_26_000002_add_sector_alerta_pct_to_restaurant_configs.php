<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->unsignedTinyInteger('sector_alerta_pct')->default(70)->after('capacidad_pct');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->dropColumn('sector_alerta_pct');
        });
    }
};
