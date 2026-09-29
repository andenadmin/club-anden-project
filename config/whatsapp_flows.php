<?php

return [

    'reserva' => [
        // false = flujo viejo por texto (rollback inmediato cambiando la env y `php artisan config:clear`).
        'enabled'   => (bool) env('WA_RESERVA_FLOW_ENABLED', false),
        'flow_id'   => env('WA_FLOW_RESERVA_ID'),
        // "draft" permite probar el Flow sin publicarlo; en producción debe ser "published".
        'mode'      => env('WA_FLOW_RESERVA_MODE', 'published'),
        'cta'       => 'Reservar mesa',
        'first_screen' => 'FECHA_PERSONAS',
        'max_dias_anticipacion' => 7,
        'max_personas_flow'     => 14,
        'token_ttl_horas'       => 24,
        'tyc_url' => env('WA_FLOW_TYC_URL', 'https://drive.google.com/file/d/14djnk1Lp5-zvc33UeIbDDmTBcXr5ub3t/view?usp=sharing'),
    ],

    // PEM de la clave privada RSA. Se acepta el PEM literal (con \n escapados) o en base64.
    'private_key'            => env('WA_FLOW_PRIVATE_KEY'),
    'private_key_passphrase' => env('WA_FLOW_PRIVATE_KEY_PASSPHRASE'),

];
