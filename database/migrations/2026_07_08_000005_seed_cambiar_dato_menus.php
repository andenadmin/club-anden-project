<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El menú "¿qué dato querés cambiar?" (restaurante/eventos/eventos-niños) nunca fue
 * editable: resCambiarSteps()/evtCambiarSteps() en BotEngine son arrays PHP
 * hardcodeados, interpretados por posición. MSG_RES_CAMBIAR/MSG_EVT_CAMBIAR/
 * MSG_EVT_NINOS_CAMBIAR en bot_messages están muertos (nunca se renderizan) —
 * los revivimos como la intro real de cada menú, y sembramos sus opciones con los
 * mismos `paso` que ya usa el código (no se inventa nada nuevo).
 *
 * Nota: resCambiarSteps() no tiene ni tuvo nunca una opción "Sector" pese a que el
 * texto muerto de MSG_RES_CAMBIAR la mencionaba — no se agrega acá; sería una
 * funcionalidad nueva, no un rename/reorder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('bot_messages')->where('key', 'MSG_RES_CAMBIAR')->update([
            'content' => '¿Qué dato querés cambiar?', 'updated_at' => $now,
        ]);
        DB::table('bot_messages')->where('key', 'MSG_EVT_CAMBIAR')->update([
            'content' => '¿Qué dato querés cambiar?', 'updated_at' => $now,
        ]);
        DB::table('bot_messages')->where('key', 'MSG_EVT_NINOS_CAMBIAR')->update([
            'content' => '¿Qué dato querés cambiar?', 'updated_at' => $now,
        ]);

        $grupos = [
            'RES_CAMBIAR_MENU' => [
                ['value' => 'fecha',              'label' => 'Fecha'],
                ['value' => 'hora',               'label' => 'Horario'],
                ['value' => 'numero_personas',    'label' => 'Cantidad de personas'],
                ['value' => 'nombre_responsable', 'label' => 'Nombre del responsable'],
                ['value' => 'mail_contacto',       'label' => 'Mail'],
            ],
            'EVT_CAMBIAR_MENU' => [
                ['value' => 'fecha',              'label' => 'Fecha'],
                ['value' => 'hora_inicio',        'label' => 'Hora de inicio'],
                ['value' => 'numero_personas',    'label' => 'Cantidad de personas'],
                ['value' => 'nombre_responsable', 'label' => 'Nombre del responsable'],
                ['value' => 'mail_contacto',       'label' => 'Mail'],
            ],
            'EVT_NINOS_CAMBIAR_MENU' => [
                ['value' => 'fecha',              'label' => 'Fecha'],
                ['value' => 'hora_inicio',        'label' => 'Hora de inicio'],
                ['value' => 'nombre_hijo',        'label' => 'Nombre del/la festejado/a'],
                ['value' => 'nombre_responsable', 'label' => 'Nombre del responsable'],
                ['value' => 'mail_contacto',       'label' => 'Mail'],
            ],
        ];

        foreach ($grupos as $optionsKey => $opciones) {
            foreach ($opciones as $i => $opt) {
                DB::table('bot_message_options')->updateOrInsert(
                    ['options_key' => $optionsKey, 'value' => $opt['value']],
                    [
                        'options_key' => $optionsKey,
                        'label'       => $opt['label'],
                        'orden'       => $i + 1,
                        'activo'      => true,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('bot_message_options')->whereIn('options_key', [
            'RES_CAMBIAR_MENU', 'EVT_CAMBIAR_MENU', 'EVT_NINOS_CAMBIAR_MENU',
        ])->delete();
    }
};
