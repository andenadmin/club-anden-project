<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_contacto')->unique();
            $table->string('nombre_cliente')->nullable();
            $table->unsignedInteger('contador_reservas_deportes')->default(0);
            $table->unsignedInteger('contador_reservas_restaurante')->default(0);
            $table->unsignedInteger('contador_reservas_eventos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
