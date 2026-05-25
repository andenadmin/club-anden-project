<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_canchas', function (Blueprint $table) {
            $table->id();
            $table->string('concepto')->unique();
            $table->string('deporte');
            $table->string('deporte_label');
            $table->string('franja_label');
            $table->string('duracion_label')->nullable();
            $table->decimal('precio', 12, 2);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_canchas');
    }
};
