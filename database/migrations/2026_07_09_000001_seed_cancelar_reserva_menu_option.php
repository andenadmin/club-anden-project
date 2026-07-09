<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega la opción "Cancelar una reserva" al menú principal (MENU_PRINCIPAL),
 * para que el cliente pueda cancelar su propia reserva por WhatsApp sin
 * depender de un asesor. BotEngine::iniciarCancelacion() siempre filtra por
 * id_cliente de la sesión, así que solo puede tocar reservas de ese mismo
 * número de contacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('bot_message_options')->updateOrInsert(
            ['options_key' => 'MENU_PRINCIPAL', 'value' => 'cancelar_reserva'],
            [
                'options_key' => 'MENU_PRINCIPAL',
                'label'       => 'Cancelar una reserva ❌',
                'orden'       => 4,
                'activo'      => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('bot_message_options')
            ->where('options_key', 'MENU_PRINCIPAL')
            ->where('value', 'cancelar_reserva')
            ->delete();
    }
};
