<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MSG_BIENVENIDA_CONOCIDO / MSG_REGISTRO_BIENVENIDA tenían la lista de opciones
 * embebida en el texto y BotEngine las interpretaba por POSICIÓN (0=deportes,
 * 1=restaurante, 2=eventos) — el mismo patrón roto que ya arreglamos para sectores.
 * A partir de ahora las opciones viven en bot_message_options (options_key
 * MENU_PRINCIPAL) y el contenido de estos dos mensajes queda solo como intro.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('bot_messages')->where('key', 'MSG_BIENVENIDA_CONOCIDO')->update([
            'content'    => "¡Hola, {{nombre}}! Bienvenido de nuevo a El Anden 🌿\n\nSoy Andy. ¿En qué puedo ayudarte hoy?",
            'updated_at' => $now,
        ]);

        DB::table('bot_messages')->where('key', 'MSG_REGISTRO_BIENVENIDA')->update([
            'content'    => "¡Mucho gusto, {{nombre}}! Ya te registré en nuestro sistema.\n\n¿En qué puedo ayudarte hoy?",
            'updated_at' => $now,
        ]);

        foreach ([
            ['value' => 'deportes',    'label' => 'Reserva tu cancha 🏅',       'orden' => 1],
            ['value' => 'restaurante', 'label' => 'Reserva tu mesa 🍽️',        'orden' => 2],
            ['value' => 'eventos',     'label' => 'Eventos / Cumpleaños 🎉',    'orden' => 3],
        ] as $opt) {
            DB::table('bot_message_options')->updateOrInsert(
                ['options_key' => 'MENU_PRINCIPAL', 'value' => $opt['value']],
                array_merge($opt, ['options_key' => 'MENU_PRINCIPAL', 'activo' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('bot_message_options')->where('options_key', 'MENU_PRINCIPAL')->delete();
    }
};
