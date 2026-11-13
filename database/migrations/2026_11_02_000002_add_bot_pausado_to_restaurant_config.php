<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->boolean('bot_pausado_global')->default(false)->after('sector_alerta_pct');
            $table->string('bot_pausado_hasta')->nullable()->after('bot_pausado_global');
            $table->string('bot_pausado_motivo')->nullable()->after('bot_pausado_hasta');
        });
    }
    public function down(): void {
        Schema::table('restaurant_config', function (Blueprint $table) {
            $table->dropColumn(['bot_pausado_global', 'bot_pausado_hasta', 'bot_pausado_motivo']);
        });
    }
};
