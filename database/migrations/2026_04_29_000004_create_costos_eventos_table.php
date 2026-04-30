<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('concepto')->unique();
            $table->string('descripcion')->nullable();
            $table->decimal('precio', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_eventos');
    }
};
