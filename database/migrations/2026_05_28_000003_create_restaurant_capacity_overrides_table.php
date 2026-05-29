<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_capacity_overrides', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->unsignedSmallInteger('salon_max')->nullable();
            $table->unsignedSmallInteger('galeria_max')->nullable();
            $table->unsignedSmallInteger('terraza_max')->nullable();
            $table->unsignedSmallInteger('parrilla_max')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_capacity_overrides');
    }
};
