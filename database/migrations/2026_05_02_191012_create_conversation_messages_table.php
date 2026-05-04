<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_session_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);                    // inbound | outbound
            $table->string('sender', 10);                       // user | bot | advisor
            $table->text('body');
            $table->string('wa_message_id')->nullable()->unique(); // ID que devuelve Meta (idempotencia)
            $table->string('wa_status', 20)->nullable();        // sent | delivered | read | failed
            $table->timestamps();

            $table->index(['bot_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
