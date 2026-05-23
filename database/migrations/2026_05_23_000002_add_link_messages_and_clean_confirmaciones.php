<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Insertar nuevos mensajes de links (editables desde /bot/messages) ──

        DB::table('bot_messages')->updateOrInsert(
            ['key' => 'MSG_LINK_TYC'],
            [
                'category'    => 'general',
                'label'       => 'Link — Términos y Condiciones',
                'content'     => "📄 *Términos y Condiciones de El Andén:*\nhttps://drive.google.com/file/d/14djnk1Lp5-zvc33UeIbDDmTBcXr5ub3t/view?usp=sharing\n\nPor favor, leelo antes de confirmar tu reserva.",
                'is_archived' => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        DB::table('bot_messages')->updateOrInsert(
            ['key' => 'MSG_LINK_CUMPLE_NINOS'],
            [
                'category'    => 'eventos',
                'label'       => 'Link — Packs cumpleaños niños',
                'content'     => "🎉 *Packs de Cumpleaños Niños — Opciones y Precios:*\nhttps://drive.google.com/file/d/1E-WP63zeEupvzXJJQv7-0337prMjena2/view?usp=drive_link",
                'is_archived' => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        DB::table('bot_messages')->updateOrInsert(
            ['key' => 'MSG_LINK_CUMPLE_ADOLESCENTES'],
            [
                'category'    => 'eventos',
                'label'       => 'Link — Packs cumpleaños adolescentes',
                'content'     => "🎉 *Packs de Cumpleaños Adolescentes — Opciones:*\nhttps://drive.google.com/file/d/1pKLIUYpNucTk8aA7XfXqSdiu-zzWmz_z/view?usp=sharing",
                'is_archived' => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        // ── Actualizar confirmaciones: quitar link TYC embebido, agregar mención T&C en SI ──

        DB::table('bot_messages')
            ->where('key', 'MSG_RES_CONFIRMACION')
            ->update([
                'content'    => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva (al confirmar aceptás los T&C)\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
                'updated_at' => now(),
            ]);

        DB::table('bot_messages')
            ->where('key', 'MSG_RES_CONFIRMACION_FUTURA')
            ->update([
                'content'    => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n⚠️ Tu reserva es para una fecha fuera de nuestro período habitual de reservas (próximos 7 días). La tomamos como *pre-confirmada*, pero un asesor deberá confirmarla. Te vamos a avisar.\n\n¿Pre-confirmamos?\n\n*SI* — Pre-confirmar (al confirmar aceptás los T&C)\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
                'updated_at' => now(),
            ]);

        DB::table('bot_messages')
            ->where('key', 'MSG_CONFIRMACION')
            ->update([
                'content'    => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva (al confirmar aceptás los T&C)\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
                'updated_at' => now(),
            ]);

        // ── Actualizar MSG_EVT_NINOS_PACK: quitar placeholder [LINK_MENU_PACKS] ──

        DB::table('bot_messages')
            ->where('key', 'MSG_EVT_NINOS_PACK')
            ->update([
                'content'    => "¡Nos encanta que elijan festejar en El Anden! 🎉\n\nEn el pack reservamos tu cancha de Fútbol 5 u 8. Hasta 20 nenes por cancha, duración máxima 2 hs y 15 min con intermedio para comer, 2 coordinadores, menú fijo + extras adicionales (no incluidos). La comida, canchas y coordinadores son obligatorios y proporcionales a la cantidad de niños.\n\n¿Qué opción elegís?\n\n*1.* Pack 1\n*2.* Pack 2\n*3.* Pack 3\n*4.* Pack 4\n\n*0.* Hablar con un asesor",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('bot_messages')->whereIn('key', [
            'MSG_LINK_TYC',
            'MSG_LINK_CUMPLE_NINOS',
            'MSG_LINK_CUMPLE_ADOLESCENTES',
        ])->delete();

        DB::table('bot_messages')->where('key', 'MSG_RES_CONFIRMACION')
            ->update(['content' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)"]);

        DB::table('bot_messages')->where('key', 'MSG_RES_CONFIRMACION_FUTURA')
            ->update(['content' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n⚠️ Tu reserva es para una fecha fuera de nuestro período habitual de reservas (próximos 7 días). La tomamos como *pre-confirmada*, pero un asesor deberá confirmarla. Te vamos a avisar.\n\n¿Pre-confirmamos?\n\n*SI* — Pre-confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)"]);

        DB::table('bot_messages')->where('key', 'MSG_CONFIRMACION')
            ->update(['content' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)"]);

        DB::table('bot_messages')->where('key', 'MSG_EVT_NINOS_PACK')
            ->update(['content' => "¡Nos encanta que elijan festejar en El Anden! 🎉\n\nEn el pack reservamos tu cancha de Fútbol 5 u 8. Hasta 20 nenes por cancha, duración máxima 2 hs y 15 min con intermedio para comer, 2 coordinadores, menú fijo + extras adicionales (no incluidos). La comida, canchas y coordinadores son obligatorios y proporcionales a la cantidad de niños.\n\nPara ver el detalle de las opciones con precios estimados: [LINK_MENU_PACKS]\n\n¿Qué opción elegís?\n\n*1.* Pack 1\n*2.* Pack 2\n*3.* Pack 3\n*4.* Pack 4\n\n*0.* Hablar con un asesor"]);
    }
};
