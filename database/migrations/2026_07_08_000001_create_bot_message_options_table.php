<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_message_options', function (Blueprint $table) {
            $table->id();
            $table->string('options_key'); // agrupa opciones que comparten menú (ej. MENU_PRINCIPAL lo usan 2 mensajes distintos)
            $table->string('value'); // fijo, nunca editable — de esto rutea el bot
            $table->string('label'); // editable — lo que lee el cliente
            $table->unsignedInteger('orden');
            $table->boolean('activo')->default(true);
            $table->json('meta')->nullable(); // datos estables extra por opción (fase 2: hora, concepto de precio, etc.)
            $table->timestamps();

            $table->unique(['options_key', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_message_options');
    }
};
