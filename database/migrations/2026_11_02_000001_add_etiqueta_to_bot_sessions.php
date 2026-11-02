<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->string('etiqueta')->default('abierta')->after('is_important');
        });
    }
    public function down(): void {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->dropColumn('etiqueta');
        });
    }
};
