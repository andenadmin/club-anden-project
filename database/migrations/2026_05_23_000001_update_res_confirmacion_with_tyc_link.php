<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TYC_LINK = 'https://drive.google.com/file/d/14djnk1Lp5-zvc33UeIbDDmTBcXr5ub3t/view?usp=sharing';

    public function up(): void
    {
        DB::table('bot_messages')
            ->where('key', 'MSG_RES_CONFIRMACION')
            ->update([
                'content' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n📄 Por favor, leé los *Términos y Condiciones* antes de confirmar:\n" . self::TYC_LINK . "\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva (al confirmar aceptás los T&C)\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            ]);

        DB::table('bot_messages')
            ->where('key', 'MSG_RES_CONFIRMACION_FUTURA')
            ->update([
                'content' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n⚠️ Tu reserva es para una fecha fuera de nuestro período habitual de reservas (próximos 7 días). La tomamos como *pre-confirmada*, pero un asesor deberá confirmarla. Te vamos a avisar.\n\n📄 Por favor, leé los *Términos y Condiciones* antes de confirmar:\n" . self::TYC_LINK . "\n\n¿Pre-confirmamos?\n\n*SI* — Pre-confirmar (al confirmar aceptás los T&C)\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            ]);
    }

    public function down(): void
    {
        DB::table('bot_messages')
            ->where('key', 'MSG_RES_CONFIRMACION')
            ->update([
                'content' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            ]);

        DB::table('bot_messages')
            ->where('key', 'MSG_RES_CONFIRMACION_FUTURA')
            ->update([
                'content' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n⚠️ Tu reserva es para una fecha fuera de nuestro período habitual de reservas (próximos 7 días). La tomamos como *pre-confirmada*, pero un asesor deberá confirmarla. Te vamos a avisar.\n\n¿Pre-confirmamos?\n\n*SI* — Pre-confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            ]);
    }
};
