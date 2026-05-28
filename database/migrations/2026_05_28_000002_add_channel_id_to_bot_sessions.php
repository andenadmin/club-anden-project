<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->foreignId('channel_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('whatsapp_channels')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('channel_id');
        });
    }
};
