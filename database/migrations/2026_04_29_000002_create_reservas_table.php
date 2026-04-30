<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cliente')->constrained('clientes')->cascadeOnDelete();
            $table->string('rama_servicio'); // DEPORTES, RESTAURANTE, EVENTOS
            $table->string('subtipo')->nullable(); // NINOS, GENERAL_EVT
            $table->string('estado_reserva'); // CONFIRMADA, PENDIENTE_CONFIRMACION, CANCELADA, ESCALADA
            $table->json('datos')->nullable();
            $table->decimal('presupuesto_total', 12, 2)->nullable();
            $table->boolean('tiene_extras')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
