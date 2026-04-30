<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('numero_contacto')->unique();
            $table->string('estado_actual')->default('INICIO');
            $table->string('rama_activa')->nullable();
            $table->string('subtipo_activo')->nullable();
            $table->string('current_step')->nullable();
            $table->unsignedInteger('contador_invalidos')->default(0);
            $table->json('datos_parciales')->nullable();
            $table->foreignId('id_cliente')->nullable()->constrained('clientes')->nullOnDelete();
            $table->timestamp('timestamp_pausa')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};
