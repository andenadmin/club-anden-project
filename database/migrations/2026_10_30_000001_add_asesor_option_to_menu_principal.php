<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('bot_message_options')->updateOrInsert(
            ['options_key' => 'MENU_PRINCIPAL', 'value' => 'asesor'],
            [
                'options_key' => 'MENU_PRINCIPAL',
                'value'       => 'asesor',
                'label'       => 'Hablar con un Asesor',
                'orden'       => 4,
                'activo'      => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('bot_message_options')
            ->where('options_key', 'MENU_PRINCIPAL')
            ->where('value', 'asesor')
            ->delete();
    }
};
