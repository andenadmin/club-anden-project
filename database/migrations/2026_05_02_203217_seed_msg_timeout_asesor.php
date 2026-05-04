<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEY = 'MSG_TIMEOUT_ASESOR';

    private const CONTENT = "¡Disculpá la demora! 🙏\n\nPasaron varias horas desde tu última consulta sin que pudiéramos terminarla. Para asegurarnos de tener tus datos al día, vamos a empezar de nuevo.\n\n¿En qué te ayudamos?";

    public function up(): void
    {
        $exists = DB::table('bot_messages')->where('key', self::KEY)->exists();
        if (!$exists) {
            DB::table('bot_messages')->insert([
                'key'        => self::KEY,
                'category'   => 'general',
                'label'      => 'Timeout de atención humana (12h)',
                'content'    => self::CONTENT,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('bot_messages')->where('key', self::KEY)->delete();
    }
};
