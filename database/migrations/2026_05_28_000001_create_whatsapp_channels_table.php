<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_channels', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();            // 'restaurante', 'eventos'
            $table->string('label');                     // 'Restaurante', 'Eventos'
            $table->string('phone_number_id')->unique(); // Meta phone number ID
            $table->text('access_token')->nullable();    // null = falls back to global config
            $table->string('default_flow')->nullable();  // null=full menu, 'EVENTOS'=skip to eventos
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_channels');
    }
};
