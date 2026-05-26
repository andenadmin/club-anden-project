<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('tipo'); // 'auto_confirm', 'sector_alerta', etc.
            $table->json('payload')->nullable();
            $table->boolean('leida')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_notifications');
    }
};
