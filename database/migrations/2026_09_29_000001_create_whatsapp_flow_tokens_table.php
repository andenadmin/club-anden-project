<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_flow_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('bot_session_id')->constrained('bot_sessions')->cascadeOnDelete();
            $table->string('flow', 40);
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('resultado', 20)->nullable(); // reserva | grupo
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_tokens');
    }
};
