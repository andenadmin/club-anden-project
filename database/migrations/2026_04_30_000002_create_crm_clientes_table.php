<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cliente')->unique()->constrained('clientes')->cascadeOnDelete();

            // Segmentación manual
            $table->json('etiquetas')->nullable();       // ['VIP','Frecuente','Corporativo','Cumpleañero']
            $table->string('canal_captacion')->nullable(); // WhatsApp, Instagram, Referido, Directo

            // Notas internas del equipo
            $table->text('notas')->nullable();

            // Marketing
            $table->boolean('opt_in_marketing')->default(false);

            // Métricas calculadas (actualizadas al confirmar reservas)
            $table->decimal('valor_lifetime', 12, 2)->default(0);
            $table->date('fecha_ultimo_evento')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_clientes');
    }
};
