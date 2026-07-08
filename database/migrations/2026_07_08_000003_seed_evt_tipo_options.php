<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MSG_EVT_01 (tipo de evento) enrutaba por dígito literal '1'-'6' hardcodeado en
 * BotEngine — renombrar/reordenar el texto no cambiaba la posición pero sí podía
 * confundir al cliente sobre qué dígito corresponde a qué opción. A partir de ahora
 * las opciones viven en bot_message_options (options_key EVT_TIPO).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('bot_messages')->where('key', 'MSG_EVT_01')->update([
            'content'    => "¡Genial! Vamos a organizar tu cumpleaños 🎉\n\n¿Qué tipo de festejo estás planeando?",
            'updated_at' => $now,
        ]);

        foreach ([
            ['value' => 'privado',      'label' => 'Evento privado',                            'orden' => 1],
            ['value' => 'futbol',       'label' => 'Fútbol (6 a 13 años)',                       'orden' => 2],
            ['value' => 'padel',        'label' => 'Pádel (hasta 16 años)',                      'orden' => 3],
            ['value' => 'hockey',       'label' => 'Hockey',                                     'orden' => 4],
            ['value' => 'adolescentes', 'label' => 'Cumpleaños adolescentes (14 a 17 años)',     'orden' => 5],
            ['value' => 'adultos',      'label' => 'Cumpleaños adultos',                         'orden' => 6],
        ] as $opt) {
            DB::table('bot_message_options')->updateOrInsert(
                ['options_key' => 'EVT_TIPO', 'value' => $opt['value']],
                array_merge($opt, ['options_key' => 'EVT_TIPO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('bot_message_options')->where('options_key', 'EVT_TIPO')->delete();
    }
};
