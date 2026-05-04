<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->string('motivo_pausa', 50)->nullable()->after('timestamp_pausa');
            $table->string('estado_previo_pausa', 50)->nullable()->after('motivo_pausa');
            $table->timestamp('next_resume_check_at')->nullable()->after('estado_previo_pausa');
            $table->timestamp('resolved_by_advisor_at')->nullable()->after('next_resume_check_at');
            $table->timestamp('last_message_at')->nullable()->after('resolved_by_advisor_at');
            $table->unsignedInteger('unread_count')->default(0)->after('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'motivo_pausa',
                'estado_previo_pausa',
                'next_resume_check_at',
                'resolved_by_advisor_at',
                'last_message_at',
                'unread_count',
            ]);
        });
    }
};
