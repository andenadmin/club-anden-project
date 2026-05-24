<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bot_messages')->updateOrInsert(
            ['key' => 'MSG_RES_FIN_DE_SEMANA'],
            [
                'category'    => 'restaurante',
                'label'       => 'Restaurante — aviso sábado/domingo/feriado',
                'content'     => "🗓️ *¡Atención — Sábado, Domingo o Feriado!*\n\nSi vas a reservar al mediodía, te avisamos que ese turno es *completo y obligatorio*, con horario fijo de *12:00 a 16:00 hs*.",
                'is_archived' => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('bot_messages')->where('key', 'MSG_RES_FIN_DE_SEMANA')->delete();
    }
};
