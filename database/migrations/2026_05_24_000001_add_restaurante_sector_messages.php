<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // MSG_RES_04 → ahora Salón / Galería / Terraza / Sin preferencia
        DB::table('bot_messages')->updateOrInsert(
            ['key' => 'MSG_RES_04'],
            [
                'category'    => 'restaurante',
                'label'       => 'Restaurante — preferencia de sector',
                'content'     => "¿En qué sector preferís sentarte?\n\n*A.* Salón\n*B.* Galería\n*C.* Terraza\n*D.* Sin preferencia\n\n*0.* Hablar con un asesor",
                'is_archived' => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]
        );

        DB::table('bot_messages')->updateOrInsert(
            ['key' => 'MSG_RES_SECTOR_LLENO'],
            [
                'category'    => 'restaurante',
                'label'       => 'Restaurante — todos los sectores sin capacidad',
                'content'     => "⚠️ En este momento todos los sectores están completos para esa fecha.\n\nUn asesor se va a comunicar con vos para ver si podemos encontrarte lugar. 🙏",
                'is_archived' => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]
        );

        DB::table('bot_messages')->updateOrInsert(
            ['key' => 'MSG_RES_FINDE_TURNOS'],
            [
                'category'    => 'restaurante',
                'label'       => 'Restaurante — turnos fin de semana / feriado',
                'content'     => "¿A qué turno querés asistir?\n\n*A.* 12:00 hs\n*B.* 14:00 hs\n\n*0.* Hablar con un asesor",
                'is_archived' => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]
        );

        DB::table('bot_messages')->updateOrInsert(
            ['key' => 'MSG_RES_FINDE_ORDEN_LLEGADA'],
            [
                'category'    => 'restaurante',
                'label'       => 'Restaurante — finde/feriado por orden de llegada',
                'content'     => "🗓️ Los sábados, domingos y feriados las reservas se toman hasta las *11:00 hs*.\n\nPasado ese horario, la atención es *por orden de llegada*. ¡Te esperamos! 🌿",
                'is_archived' => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('bot_messages')->whereIn('key', [
            'MSG_RES_SECTOR_LLENO',
            'MSG_RES_FINDE_TURNOS',
            'MSG_RES_FINDE_ORDEN_LLEGADA',
        ])->delete();
    }
};
