<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MSG_EVT_07 (a nombre de quién) enrutaba asumiendo que la primera opción SIEMPRE
 * significa "usar mi nombre" (`$upper07 === $keys07[0]`) — se rompe si se reordena
 * el texto. A partir de ahora vive en bot_message_options (options_key
 * EVT_NOMBRE_RESPONSABLE).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('bot_messages')->where('key', 'MSG_EVT_07')->update([
            'content'    => '¿A nombre de quién registramos el evento?',
            'updated_at' => $now,
        ]);

        foreach ([
            ['value' => 'mi_nombre',   'label' => 'Mi nombre (uso el nombre con el que estoy registrado)', 'orden' => 1],
            ['value' => 'otro_nombre', 'label' => 'Ingresar otro nombre',                                  'orden' => 2],
        ] as $opt) {
            DB::table('bot_message_options')->updateOrInsert(
                ['options_key' => 'EVT_NOMBRE_RESPONSABLE', 'value' => $opt['value']],
                array_merge($opt, ['options_key' => 'EVT_NOMBRE_RESPONSABLE', 'activo' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('bot_message_options')->where('options_key', 'EVT_NOMBRE_RESPONSABLE')->delete();
    }
};
