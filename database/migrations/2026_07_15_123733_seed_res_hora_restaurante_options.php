<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convierte los turnos de hora del restaurante (MSG_RES_02) en opciones editables
 * desde el panel, igual que el menú principal o los sectores.
 *
 * Regla para los labels: deben incluir la hora en formato "XX hs" o "XX:XX hs"
 * para que BotEngine::extractHoraDeLabel() la pueda extraer automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Actualizar el mensaje en BD a solo el intro (las opciones se agregan dinámicamente)
        DB::table('bot_messages')->where('key', 'MSG_RES_02')->update([
            'content'    => '¿A qué hora querés llegar?',
            'updated_at' => $now,
        ]);

        $opciones = [
            ['label' => 'Turno Brunch/Almuerzo mediodía 1: 11.45 hs (hasta las 14 hs)'],
            ['label' => 'Turno mediodía 2: 14 hs'],
            ['label' => 'Turno mediodía completo: 12 hs (hasta las 16 hs — con menú fijo)'],
            ['label' => 'Turno noche 1: 20 hs'],
            ['label' => 'Turno noche 2: 22 hs'],
        ];

        foreach ($opciones as $i => $opt) {
            DB::table('bot_message_options')->updateOrInsert(
                ['options_key' => 'RES_HORA_RESTAURANTE', 'label' => $opt['label']],
                [
                    'options_key' => 'RES_HORA_RESTAURANTE',
                    'value'       => 'turno_' . ($i + 1),
                    'label'       => $opt['label'],
                    'orden'       => $i + 1,
                    'activo'      => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('bot_message_options')->where('options_key', 'RES_HORA_RESTAURANTE')->delete();

        DB::table('bot_messages')->where('key', 'MSG_RES_02')->update([
            'content'    => "¿A qué hora querés llegar?\n\n*A.* Turno mediodía 1: 11.30 hs (hasta las 14 hs)\n*B.* Turno mediodía 2: 14 hs\n*C.* Turno mediodía completo: 12 hs (hasta las 16 hs — con menú fijo)\n*D.* Turno noche 1: 20 hs\n*E.* Turno noche 2: 22 hs\n\n*0.* Hablar con un asesor",
            'updated_at' => now(),
        ]);
    }
};
