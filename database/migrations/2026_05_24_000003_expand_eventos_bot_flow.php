<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── Costos nuevos ────────────────────────────────────────────────────────
        $costos = [
            // Pádel
            ['concepto' => 'padel_cancha',              'descripcion' => 'Cancha de pádel (1 hora)',              'precio' => 38000],
            ['concepto' => 'padel_coordinador',         'descripcion' => 'Coordinador pádel (hasta 20hs)',        'precio' => 30000],
            ['concepto' => 'padel_coordinador_noche',   'descripcion' => 'Coordinador pádel (después de 20hs)',   'precio' => 35000],
            ['concepto' => 'padel_menu_juvenil',        'descripcion' => 'Menú juvenil pádel por chico',         'precio' => 18500],
            // Menús adultos completos
            ['concepto' => 'menu_adulto_parrillada',    'descripcion' => 'Menú adulto Parrillada Criolla',        'precio' => 57000],
            ['concepto' => 'menu_adulto_pernil',        'descripcion' => 'Menú adulto Pernil',                   'precio' => 38000],
            ['concepto' => 'menu_adulto_milanesa',      'descripcion' => 'Menú adulto Milanesa Completa',         'precio' => 38000],
            ['concepto' => 'menu_adulto_pizza',         'descripcion' => 'Menú adulto Pizza Andén',              'precio' => 33000],
        ];
        foreach ($costos as $c) {
            DB::table('costos_eventos')->updateOrInsert(['concepto' => $c['concepto']], array_merge($c, ['created_at' => $now, 'updated_at' => $now]));
        }

        // ── Mensajes bot ─────────────────────────────────────────────────────────
        $msgs = [
            [
                'key'      => 'MSG_EVT_01',
                'category' => 'eventos',
                'label'    => 'Eventos — tipo de evento',
                'content'  => "¡Genial! Vamos a organizar tu cumpleaños 🎉\n\n¿Qué tipo de festejo estás planeando?\n\n*1.* Evento privado\n*2.* Fútbol (6 a 13 años)\n*3.* Pádel (hasta 16 años)\n*4.* Hockey\n*5.* Cumpleaños adolescentes (14 a 17 años)\n*6.* Cumpleaños adultos\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_MODALIDAD',
                'category' => 'eventos',
                'label'    => 'Eventos — modalidad fútbol',
                'content'  => "¿Qué modalidad querés para el cumple?\n\n*1.* Combo Futbolero — solo fútbol\n*2.* Combo Animación Deportiva — fútbol + juegos + competencias\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_NOMBRE_HIJO',
                'category' => 'eventos',
                'label'    => 'Eventos — nombre y edad del festejado',
                'content'  => "¿Cuál es el nombre del/la festejado/a y qué edad va a cumplir?\n\nEscribilo así: *Nombre, edad* (ejemplo: *Lucas, 8*)",
            ],
            [
                'key'      => 'MSG_EVT_COLEGIO',
                'category' => 'eventos',
                'label'    => 'Eventos — colegio',
                'content'  => "¿En qué colegio está?",
            ],
            [
                'key'      => 'MSG_EVT_NECESIDADES_ESPECIALES',
                'category' => 'eventos',
                'label'    => 'Eventos — necesidades alimentarias especiales',
                'content'  => "⚠️ ¿Alguno de los chicos tiene alguna *necesidad alimentaria especial*?\n\n(celiaquía, diabetes, alergias, restricciones de dieta, etc.)\n\nSi hay alguna, describila brevemente.\nSi no hay ninguna, respondé *ninguna*.",
            ],
            [
                'key'      => 'MSG_EVT_INFO_PADEL',
                'category' => 'eventos',
                'label'    => 'Eventos — info cumpleaños pádel',
                'content'  => "🎾 *Cumpleaños Pádel — El Andén*\n\n🏟️ 1 hora de pádel por cancha (máx. 4 chicos)\n👨‍🏫 Coordinador obligatorio (menores de 16)\n🍽️ Menú incluido con bebida y servicio de mesa\n⚠️ No se permite ingresar comida, bebidas ni decoración del exterior\n\n¡Seguimos con la reserva!",
            ],
            [
                'key'      => 'MSG_EVT_INFO_HOCKEY',
                'category' => 'eventos',
                'label'    => 'Eventos — info cumpleaños hockey',
                'content'  => "🏒 *Cumpleaños Hockey — El Andén*\n\nTe recopilamos los datos para coordinar tu cumple de hockey. Un asesor va a confirmar la disponibilidad y el presupuesto final.\n\n¡Seguimos!",
            ],
            [
                'key'      => 'MSG_EVT_MENU_PADEL',
                'category' => 'eventos',
                'label'    => 'Eventos — menú pádel (chicos)',
                'content'  => "¿Qué menú preferís para los chicos?\n\n(Se elige 1 opción para todo el grupo. Incluye bebida y servicio de mesa.)\n\n*1.* Picada Andén caliente con dips\n*2.* 2 porciones de pizza\n*3.* Súper pancho\n*4.* Hamburguesa 100% carne\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_MENU_ADULTOS_TIPO',
                'category' => 'eventos',
                'label'    => 'Eventos — tipo de menú adultos',
                'content'  => "¿Qué menú completo querés para los adultos?\n\n*1.* 🥩 Parrillada Criolla Andén\n*2.* 🍖 Menú Pernil\n*3.* 🍗 Menú Milanesa Completa Club\n*4.* 🍕 Menú Pizza Andén\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_ADULTOS',
                'category' => 'eventos',
                'label'    => 'Eventos — cantidad de adultos',
                'content'  => "¿Cuántos adultos van a asistir al evento?\n\nIngresá un número (puede ser 0).\n\nℹ️ Hasta 10 adultos al almuerzo/cena: eligen de la carta el día del evento.\n   Más de 10 adultos: menú fijo completo obligatorio.\n   Merienda (16–19:30 hs): hasta 15 adultos a elección.",
            ],
            [
                'key'      => 'MSG_EVT_NINOS_CAMBIAR',
                'category' => 'eventos',
                'label'    => 'Eventos — qué dato cambiar (niños/fútbol/pádel/hockey)',
                'content'  => "¿Qué dato querés cambiar?\n\n*1.* Fecha\n*2.* Hora de inicio\n*3.* Nombre del/la festejado/a\n*4.* Nombre del responsable\n*5.* Mail\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_LINK_CUMPLE_PADEL',
                'category' => 'eventos',
                'label'    => 'Link — pack cumpleaños pádel',
                'content'  => "🎾 *Packs de Cumpleaños Pádel — Opciones y Precios:*\n_(link próximamente)_",
            ],
            [
                'key'      => 'MSG_LINK_CUMPLE_HOCKEY',
                'category' => 'eventos',
                'label'    => 'Link — pack cumpleaños hockey',
                'content'  => "🏒 *Packs de Cumpleaños Hockey — Opciones y Precios:*\n_(link próximamente)_",
            ],
        ];

        foreach ($msgs as $m) {
            DB::table('bot_messages')->updateOrInsert(
                ['key' => $m['key']],
                array_merge($m, ['is_archived' => false, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('bot_messages')->whereIn('key', [
            'MSG_EVT_MODALIDAD', 'MSG_EVT_NOMBRE_HIJO', 'MSG_EVT_COLEGIO',
            'MSG_EVT_NECESIDADES_ESPECIALES', 'MSG_EVT_INFO_PADEL', 'MSG_EVT_INFO_HOCKEY',
            'MSG_EVT_MENU_PADEL', 'MSG_EVT_MENU_ADULTOS_TIPO',
            'MSG_LINK_CUMPLE_PADEL', 'MSG_LINK_CUMPLE_HOCKEY',
        ])->delete();
    }
};
