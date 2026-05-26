<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Add menu_ninos cost (replaces pack_1/2/3/4_menu)
        DB::table('costos_eventos')->updateOrInsert(
            ['concepto' => 'menu_ninos'],
            ['descripcion' => 'Menú niños por chico', 'precio' => 5000, 'created_at' => $now, 'updated_at' => $now]
        );

        // Update MSG_EVT_NINOS_PACK: remove pack options, keep as info message only
        DB::table('bot_messages')
            ->where('key', 'MSG_EVT_NINOS_PACK')
            ->update([
                'content'    => "¡Nos encanta que elijan festejar en El Andén! 🎉\n\nReservamos tu cancha de Fútbol 5 u 8. Hasta 20 nenes por cancha, duración máxima 2 hs y 15 min con intermedio para comer, 2 coordinadores, menú fijo + extras adicionales (no incluidos). La comida, canchas y coordinadores son obligatorios y proporcionales a la cantidad de niños.",
                'updated_at' => $now,
            ]);

        // Update MSG_EVT_COSTO_MENU: remove {{pack_label}}
        DB::table('bot_messages')
            ->where('key', 'MSG_EVT_COSTO_MENU')
            ->update([
                'content'    => "Para {{numero_ninos}} niños, el costo estimado del menú es de \${{costo_menu_calculado}}. 🧮\n\nA continuación te hacemos algunas preguntas más para completar tu presupuesto.",
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $now = now();

        DB::table('bot_messages')
            ->where('key', 'MSG_EVT_NINOS_PACK')
            ->update([
                'content'    => "¡Nos encanta que elijan festejar en El Anden! 🎉\n\nEn el pack reservamos tu cancha de Fútbol 5 u 8. Hasta 20 nenes por cancha, duración máxima 2 hs y 15 min con intermedio para comer, 2 coordinadores, menú fijo + extras adicionales (no incluidos). La comida, canchas y coordinadores son obligatorios y proporcionales a la cantidad de niños.\n\n¿Qué opción elegís?\n\n*1.* Pack 1\n*2.* Pack 2\n*3.* Pack 3\n*4.* Pack 4\n\n*0.* Hablar con un asesor",
                'updated_at' => $now,
            ]);

        DB::table('bot_messages')
            ->where('key', 'MSG_EVT_COSTO_MENU')
            ->update([
                'content'    => "Para {{numero_ninos}} niños con {{pack_label}}, el costo estimado del menú es de \${{costo_menu_calculado}}. 🧮\n\nA continuación te hacemos algunas preguntas más para completar tu presupuesto.",
                'updated_at' => $now,
            ]);
    }
};
