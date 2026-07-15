<?php

namespace App\Services;

/**
 * Fuente de verdad de qué se le permite tocar al admin en cada grupo de opciones.
 * allowAddRemove=false es lo que técnicamente impide inventar una rama de negocio
 * nueva en un menú Tier 2 — el endpoint de guardado solo acepta label/orden/activo.
 */
class BotMessageOptionsRegistry
{
    public static function config(): array
    {
        return [
            'MENU_PRINCIPAL'          => ['style' => 'letter', 'allowAddRemove' => false, 'metaFields' => []],
            'EVT_TIPO'                => ['style' => 'number', 'allowAddRemove' => false, 'metaFields' => []],
            'EVT_NOMBRE_RESPONSABLE'  => ['style' => 'number', 'allowAddRemove' => false, 'metaFields' => []],
            'RES_CAMBIAR_MENU'        => ['style' => 'number', 'allowAddRemove' => false, 'metaFields' => []],
            'EVT_CAMBIAR_MENU'        => ['style' => 'number', 'allowAddRemove' => false, 'metaFields' => []],
            'EVT_NINOS_CAMBIAR_MENU'  => ['style' => 'number', 'allowAddRemove' => false, 'metaFields' => []],
            'RES_HORA_RESTAURANTE'    => ['style' => 'letter', 'allowAddRemove' => true, 'hint' => 'El texto de cada opción debe incluir la hora en formato "XX hs" o "XX:XX hs" para que el bot la reconozca. La letra (A, B, C…) se asigna automáticamente por orden de posición.', 'metaFields' => []],
        ];
    }

    public static function get(string $optionsKey): ?array
    {
        return self::config()[$optionsKey] ?? null;
    }

    /**
     * Qué options_key le corresponde a cada mensaje de bot_messages (para saber qué
     * editor de opciones mostrar debajo de cada card del panel). Varios mensajes
     * pueden compartir un mismo options_key (ej. MSG_BIENVENIDA_CONOCIDO y
     * MSG_REGISTRO_BIENVENIDA son el mismo menú mostrado en dos momentos distintos).
     */
    public static function messageKeyToOptionsKey(): array
    {
        return [
            'MSG_BIENVENIDA_CONOCIDO' => 'MENU_PRINCIPAL',
            'MSG_REGISTRO_BIENVENIDA' => 'MENU_PRINCIPAL',
            'MSG_EVT_01'              => 'EVT_TIPO',
            'MSG_EVT_07'              => 'EVT_NOMBRE_RESPONSABLE',
            'MSG_RES_CAMBIAR'         => 'RES_CAMBIAR_MENU',
            'MSG_EVT_CAMBIAR'         => 'EVT_CAMBIAR_MENU',
            'MSG_EVT_NINOS_CAMBIAR'   => 'EVT_NINOS_CAMBIAR_MENU',
            'MSG_RES_02'              => 'RES_HORA_RESTAURANTE',
        ];
    }

    public static function optionsKeyForMessage(string $messageKey): ?string
    {
        return self::messageKeyToOptionsKey()[$messageKey] ?? null;
    }
}
