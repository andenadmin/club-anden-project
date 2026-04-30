<?php

namespace App\Services;

use App\Models\BotMessage;
use Carbon\Carbon;

class BotMessages
{
    private static array $cache = [];

    // Reemplaza {{variable}} con valores del array $vars
    public static function render(string $id, array $vars = []): string
    {
        $template = self::template($id);
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }

    private static function template(string $id): string
    {
        // Try DB first (with per-request cache)
        if (!isset(self::$cache[$id])) {
            $row = BotMessage::findByKey($id);
            self::$cache[$id] = $row?->content;
        }
        if (self::$cache[$id] !== null) {
            return self::$cache[$id];
        }

        // Fallback to hardcoded defaults
        return match($id) {
            'MSG_BIENVENIDA_CONOCIDO' => "¡Hola, {{nombre}}! Bienvenido de nuevo a El Anden 🌿\n\nSoy Andy. ¿En qué puedo ayudarte hoy?\n\n*A.* Reserva tu cancha 🏅\n*B.* Reserva tu mesa 🍽️\n*C.* Eventos / Cumpleaños 🎉\n\n*0.* Hablar con un asesor\n\nRespondé con la letra de tu elección.",
            'MSG_REGISTRO_PEDIR_NOMBRE' => "¡Hola! Bienvenido a El Anden 🌿\nSoy Andy, el asistente de reservas.\n\nPara empezar, ¿cómo te llamás?",
            'MSG_REGISTRO_BIENVENIDA' => "¡Mucho gusto, {{nombre}}! Ya te registré en nuestro sistema.\n\n¿En qué puedo ayudarte hoy?\n\n*A.* Reserva tu cancha 🏅\n*B.* Reserva tu mesa 🍽️\n*C.* Eventos / Cumpleaños 🎉\n\n*0.* Hablar con un asesor\n\nRespondé con la letra de tu elección.",
            'MSG_OPCION_INVALIDA' => "No reconocí esa opción. Por favor elegí una de las opciones disponibles e intentá nuevamente.",
            'MSG_ESCALADO_HUMANO' => "Entendido. En breve un asesor de El Anden se va a comunicar con vos.\n\nMientras tanto, el asistente automático queda en pausa.\n¡Hasta pronto! 😊",

            // Deportes
            'MSG_DEP_01' => "Contamos con canchas de Fútbol 5 y 8, Pádel y Tenis, podés ver las que quedan disponibles y reservar en https://atcsports.io/venues/el-anden-caba.\n\nLas canchas están disponibles de *8 a 24 hs*.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\nSi necesitás algo más por fuera de la página, escribí \"hablar con un asesor\".",

            // Restaurante
            'MSG_RES_01' => self::buildFechaRestaurante(),
            'MSG_RES_02' => "¿A qué hora querés llegar?\n\n*1.* Turno 1: 12.30 hs\n*2.* Turno 2: 14 hs\n*3.* Turno 3: 20 hs\n*4.* Turno 4: 22 hs\n\n*0.* Hablar con un asesor",
            'MSG_RES_03' => "¿Para cuántas personas es la reserva?\n\n*1.* 1 a 2 personas\n*2.* 3 a 4 personas\n*3.* 5 a 6 personas\n*4.* 7 a 8 personas\n*5.* Más de 8 personas\n\n*0.* Hablar con un asesor",
            'MSG_RES_04' => "¿Tenés preferencia de sector?\n\n*1.* Interior\n*2.* Exterior\n*3.* Sin preferencia\n\n*0.* Hablar con un asesor",
            'MSG_RES_05' => "¿A nombre de quién reservamos la mesa?\n\n*1.* Mi nombre (uso el nombre con el que estoy registrado)\n*2.* Ingresar otro nombre\n\n*0.* Hablar con un asesor",
            'MSG_RES_05_CUSTOM' => "Por favor ingresá el nombre del responsable de la reserva:",
            'MSG_RES_06' => "¿Cuál es tu mail? Lo usamos para enviarte la confirmación y un recordatorio de tu reserva.\n\nIngresá tu dirección de correo electrónico.",
            'MSG_RES_MAIL_INVALIDO' => "El mail ingresado no parece tener un formato válido. Por favor ingresá una dirección de correo electrónico correcta (ejemplo: nombre@dominio.com).",
            'MSG_RES_CONFIRMACION' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            'MSG_RES_CONFIRMACION_FUTURA' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n⚠️ Tu reserva es para una fecha fuera de nuestro período habitual de reservas (próximos 7 días). La tomamos como *pre-confirmada*, pero un asesor deberá confirmarla. Te vamos a avisar.\n\n¿Pre-confirmamos?\n\n*SI* — Pre-confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            'MSG_RES_CAMBIAR' => "¿Qué dato querés cambiar?\n\n*1.* Fecha\n*2.* Horario\n*3.* Cantidad de personas\n*4.* Sector\n*5.* Nombre del responsable\n*6.* Mail\n\n*0.* Hablar con un asesor",

            // Eventos
            'MSG_EVT_01' => "¡Genial! Vamos a organizar tu evento 🎉\n\n¿Qué tipo de evento estás planeando?\n\n*1.* Evento privado (te contactamos con un asesor)\n*2.* Cumpleaños niños (6 a 12 años)\n*3.* Cumpleaños adolescentes (13 a 17 años)\n*4.* Cumpleaños adultos\n\n*0.* Hablar con un asesor",
            'MSG_EVT_NINOS_PACK' => "¡Nos encanta que elijan festejar en El Anden! 🎉\n\nEn el pack reservamos tu cancha de Fútbol 5 u 8. Hasta 20 nenes por cancha, duración máxima 2 hs y 15 min con intermedio para comer, 2 coordinadores, menú fijo + extras adicionales (no incluidos). La comida, canchas y coordinadores son obligatorios y proporcionales a la cantidad de niños.\n\nPara ver el detalle de las opciones con precios estimados: [LINK_MENU_PACKS]\n\n¿Qué opción elegís?\n\n*1.* Pack 1\n*2.* Pack 2\n*3.* Pack 3\n*4.* Pack 4\n\n*0.* Hablar con un asesor",
            'MSG_EVT_02' => "¿Para qué fecha es el evento?\n\nIngresá la fecha en formato DD/MM/AA (ejemplo: 15/08/26).\n\nEscribí *0* para hablar con un asesor.",
            'MSG_EVT_FERIADO_AVISO' => "⚠️ *La fecha elegida es feriado nacional.*\n\nSe aplicará un recargo del *30%* sobre el costo total del evento. El presupuesto final ya incluye este recargo.",
            'MSG_EVT_03_ENTERO' => "¿A qué hora comienza el evento?\n\nIngresá la hora de inicio como número entero entre 8 y 23 (ejemplo: 20).\n\nEscribí *0* para hablar con un asesor.",
            'MSG_EVT_03_HHMM' => "¿A qué hora comienza el evento?\n\nIngresá la hora en formato HH:MM en 24 hs (ejemplo: 20:00).\n\nEscribí *0* para hablar con un asesor.",
            'MSG_EVT_05' => "¿Cuántos niños van a participar?\n\nIngresá un número entre 1 y 50.\nSi son más de 50, escribí *0* para hablar con un asesor.",
            'MSG_EVT_COSTO_MENU' => "Para {{numero_ninos}} niños con {{pack_label}}, el costo estimado del menú es de \${{costo_menu_calculado}}. 🧮\n\nA continuación te hacemos algunas preguntas más para completar tu presupuesto.",
            'MSG_EVT_MENU' => "¿Qué menú preferís para los chicos?\n\n*1.* 2 porciones de pizza\n*2.* Pancho\n*3.* Hamburguesa\n\n*0.* Hablar con un asesor",
            'MSG_EVT_ADULTOS' => "¿Cuántos adultos van a asistir al evento?\n\nIngresá un número (puede ser 0).\n\nℹ️ Los adultos pueden optar por:\n• Menú fijo: \${{precio_menu_adulto}} por persona (se suma al presupuesto)\n• Cafetería libre: disponible en el lugar, no incluida en el pack",
            'MSG_EVT_MENU_ADULTOS' => "De los {{numero_adultos}} adultos, ¿cuántos van a tomar el menú fijo?\n\nIngresá un número entre 0 y {{numero_adultos}}.",
            'MSG_EVT_ADICIONALES' => "¿Querés agregar alimentos adicionales al evento? (no incluidos en el pack base)\n\n*1.* 🍟 Bandejas de Papas Fritas Calientes\n*2.* 🥪 Sándwiches de Miga\n*3.* 🍉 Bandejas de Frutas\n*4.* 🍦 Helados (Palito de Agua o Bombón Helado)\n\nPodés elegir varias opciones separando los números con coma (ejemplo: *1,3*).\nSi no querés adicionales, respondé *ninguno*.\n\n*0.* Hablar con un asesor",
            'MSG_EVT_ADICIONAL_QTY' => "¿Cuántas {{item_name}} querés agregar?\n\nIngresá un número entero.",
            'MSG_EVT_EXTRAS' => "¿Hay algo especial que quieras agregar o consultar para el evento?\n\nPodés escribirlo libremente. Un asesor lo va a revisar y confirmar.\nSi no tenés extras, respondé *ninguno*.",
            'MSG_EVT_MAIL' => "¿Cuál es tu mail de contacto? Lo usamos para enviarte la confirmación del evento.\n\nIngresá tu dirección de correo, o escribí *no* para omitir.",
            'MSG_EVT_07' => "¿A nombre de quién registramos el evento?\n\n*1.* Mi nombre (uso el nombre con el que estoy registrado)\n*2.* Ingresar otro nombre\n\n*0.* Hablar con un asesor",
            'MSG_EVT_07_CUSTOM' => "Por favor ingresá el nombre del responsable del evento:",
            'MSG_EVT_PERSONAS' => "¿Cuántas personas van a asistir?\n\nIngresá un número entero (1 a 999).\n\nEscribí *0* para hablar con un asesor.",
            'MSG_CONFIRMACION' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            'MSG_RESERVA_EXITOSA' => "✅ ¡Tu reserva está confirmada!\n\nGuardamos todos los datos. Si necesitás hacer algún cambio o tenés alguna consulta, no dudes en escribirnos.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\n¡Hasta pronto en El Anden! 🌿",
            'MSG_RESERVA_PRECONFIRMADA' => "✅ ¡Tu pre-reserva fue registrada!\n\nUn asesor de El Anden se va a comunicar con vos para confirmarla.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\n¡Hasta pronto! 🌿",
            default => "Lo siento, ocurrió un error interno. Por favor contactá a un asesor.",
        };
    }

    private static function buildFechaRestaurante(): string
    {
        $lines = [];
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->addDays($i);
            $label = match($i) {
                0 => 'Hoy',
                1 => 'Mañana',
                default => 'En ' . $i . ' días',
            };
            $lines[] = '*' . ($i + 1) . '.* ' . $date->format('d/m/y') . ' (' . $label . ')';
        }
        return "¡Perfecto! Vamos a reservar tu mesa 🍽️\n\n¿Para qué fecha querés reservar?\n\n"
            . implode("\n", $lines)
            . "\n\n*0.* Hablar con un asesor";
    }

    // Mapeo de opciones de fecha RESTAURANTE a fecha real
    /**
     * Parsea las opciones *KEY.* o *KEY* de un mensaje renderizado.
     * Devuelve ['KEY' => 'label', ...] en mayúsculas.
     */
    public static function parseOptions(string $id, array $vars = []): array
    {
        $rendered = self::render($id, $vars);
        $options  = [];
        preg_match_all(
            '/^\*([A-Za-z0-9]+)\.?\*[ \t]*(.+)/m',
            $rendered,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $m) {
            $options[strtoupper(trim($m[1]))] = trim($m[2]);
        }
        return $options;
    }

    /** Devuelve el label de la opción o null si no existe. */
    public static function resolveOption(string $id, string $input, array $vars = []): ?string
    {
        $opts = self::parseOptions($id, $vars);
        return $opts[strtoupper(trim($input))] ?? null;
    }

    public static function resolveFechaRestaurante(string $opcion): ?string
    {
        $idx = (int) $opcion - 1;
        if ($idx < 0 || $idx > 6) return null;
        return Carbon::now()->addDays($idx)->format('d/m/y');
    }

    public static function horaRestaurante(string $opcion): ?string
    {
        return match($opcion) {
            '1' => '12:30 hs',
            '2' => '14:00 hs',
            '3' => '20:00 hs',
            '4' => '22:00 hs',
            default => null,
        };
    }

    public static function personasRestaurante(string $opcion): ?string
    {
        return match($opcion) {
            '1' => '1 a 2 personas',
            '2' => '3 a 4 personas',
            '3' => '5 a 6 personas',
            '4' => '7 a 8 personas',
            '5' => 'Más de 8 personas',
            default => null,
        };
    }

    public static function sectorRestaurante(string $opcion): ?string
    {
        return match($opcion) {
            '1' => 'Interior',
            '2' => 'Exterior',
            '3' => 'Sin preferencia',
            default => null,
        };
    }

    public static function packLabel(string $opcion): string
    {
        return match($opcion) {
            '1' => 'Pack 1',
            '2' => 'Pack 2',
            '3' => 'Pack 3',
            '4' => 'Pack 4',
            default => 'Pack ' . $opcion,
        };
    }

    public static function menuNinosLabel(string $opcion): string
    {
        return match($opcion) {
            '1' => '2 porciones de pizza',
            '2' => 'Pancho',
            '3' => 'Hamburguesa',
            default => 'Opción ' . $opcion,
        };
    }

    public static function tipoEventoLabel(string $opcion): string
    {
        return match($opcion) {
            '1' => 'Evento privado',
            '2' => 'Cumpleaños niños (6 a 12 años)',
            '3' => 'Cumpleaños adolescentes (13 a 17 años)',
            '4' => 'Cumpleaños adultos',
            default => 'Tipo ' . $opcion,
        };
    }

    public static function adicionalLabel(int $id): string
    {
        return match($id) {
            1 => '🍟 Bandejas de Papas Fritas Calientes',
            2 => '🥪 Sándwiches de Miga',
            3 => '🍉 Bandejas de Frutas',
            4 => '🍦 Helados',
            default => 'Adicional ' . $id,
        };
    }

    public static function adicionalConcepto(int $id): string
    {
        return match($id) {
            1 => 'adicional_papas',
            2 => 'adicional_sandwiches',
            3 => 'adicional_frutas',
            4 => 'adicional_helados',
            default => 'adicional_' . $id,
        };
    }
}
