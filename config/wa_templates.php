<?php

/*
|--------------------------------------------------------------------------
| Plantillas de WhatsApp aprobadas en Meta Business Manager
|--------------------------------------------------------------------------
|
| Cada entrada define una plantilla disponible para que los asesores
| puedan enviar desde el inbox cuando la ventana de 24hs esté cerrada.
|
| IMPORTANTE: el campo "name" debe coincidir EXACTAMENTE con el nombre
| aprobado en Meta Business Manager (minúsculas, sin espacios).
|
| Variables: se definen en orden, coincidiendo con {{1}}, {{2}}, etc.
| en el cuerpo de la plantilla aprobada.
|
*/

return [

    [
        'id'          => 'recordatorio_evento',
        'name'        => 'recordatorio_evento',
        'language'    => 'es_AR',
        'label'       => 'Recordatorio de evento',
        'description' => 'Recordar al cliente su evento próximo con opciones de info o cancelación',
        'preview'     => 'Hola {{nombre}}, te recordamos que el evento *{{evento}}* es el próximo {{fecha}}. Para confirmar tu lugar o consultar, respondé este mensaje. ¡Te esperamos!',
        'variables'   => [
            ['key' => 'nombre', 'label' => 'Nombre del cliente'],
            ['key' => 'evento', 'label' => 'Nombre del evento'],
            ['key' => 'fecha',  'label' => 'Fecha (ej: 14 de junio)'],
        ],
        'buttons'     => [
            ['type' => 'QUICK_REPLY', 'text' => 'INFO'],
            ['type' => 'QUICK_REPLY', 'text' => 'CANCELAR'],
        ],
    ],

    [
        'id'          => 'recordatorio_reserva',
        'name'        => 'recordatorio_reserva',   // ← reemplazar con nombre real aprobado
        'language'    => 'es_AR',
        'label'       => 'Recordatorio de reserva',
        'description' => 'Recordar al cliente su reserva próxima',
        'preview'     => 'Hola {{nombre}}, te recordamos tu reserva en Club El Andén para el {{fecha}} a las {{hora}}. Si necesitás modificarla o cancelarla, respondé este mensaje.',
        'variables'   => [
            ['key' => 'nombre', 'label' => 'Nombre del cliente'],
            ['key' => 'fecha',  'label' => 'Fecha (ej: viernes 30/05)'],
            ['key' => 'hora',   'label' => 'Hora (ej: 20:30)'],
        ],
    ],

    [
        'id'          => 'confirmacion_reserva',
        'name'        => 'confirmacion_reserva',   // ← reemplazar con nombre real aprobado
        'language'    => 'es_AR',
        'label'       => 'Confirmación de reserva',
        'description' => 'Confirmar al cliente que su reserva está lista',
        'preview'     => '✅ ¡Tu reserva en Club El Andén está confirmada! Te esperamos el {{fecha}} a las {{hora}}. Ante cualquier cambio, respondé este mensaje.',
        'variables'   => [
            ['key' => 'fecha', 'label' => 'Fecha (ej: viernes 30/05)'],
            ['key' => 'hora',  'label' => 'Hora (ej: 20:30)'],
        ],
    ],

    [
        'id'          => 'recontacto',
        'name'        => 'recontacto',              // ← reemplazar con nombre real aprobado
        'language'    => 'es_AR',
        'label'       => 'Recontacto',
        'description' => 'Retomar una conversación inactiva',
        'preview'     => 'Hola {{nombre}}, somos Club El Andén. ¿Podemos ayudarte con algo? Respondé este mensaje para chatear con nosotros. 🌿',
        'variables'   => [
            ['key' => 'nombre', 'label' => 'Nombre del cliente'],
        ],
    ],

    [
        'id'          => 'saludo_inicial',
        'name'        => 'saludo_inicial',
        'language'    => 'es_AR',
        'label'       => 'Saludo inicial',
        'description' => 'Iniciar contacto con un usuario nuevo o retomar después de más de 24 horas',
        'preview'     => "¡Hola! 👋 Un asesor quiere comunicarse con vos,",
        'variables'   => [],
        'buttons'     => [
            ['type' => 'QUICK_REPLY', 'text' => '¡Charlemos!'],
        ],
    ],

];
