<?php

namespace App\Services;

use App\Models\BotMessage;
use Carbon\Carbon;

class BotMessages
{
    private static array $cache       = [];
    private static array $archivedKeys = [];
    private static bool  $loaded      = false;

    private static function loadAll(): void
    {
        if (self::$loaded) return;
        self::$loaded = true;
        BotMessage::each(function ($row) {
            if ($row->is_archived) {
                self::$archivedKeys[$row->key] = true;
            } else {
                self::$cache[$row->key] = $row->content;
            }
        });
    }

    // Reemplaza {{variable}} con valores del array $vars
    public static function render(string $id, array $vars = []): string
    {
        $template = self::template($id);
        if ($template === '') return '';
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }

    private static function template(string $id): string
    {
        self::loadAll();

        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }

        // Mensaje explícitamente archivado por el admin → no enviar
        if (isset(self::$archivedKeys[$id])) {
            \Illuminate\Support\Facades\Log::warning('[BOT][MESSAGES] Mensaje archivado suprimido en flujo', ['key' => $id]);
            return '';
        }

        if ($id === 'MSG_RES_01') return self::buildFechaRestaurante();

        return self::hardcodedDefault($id) ?? "Lo siento, ocurrió un error interno. Por favor contactá a un asesor.";
    }

    public static function clearCache(): void
    {
        self::$cache       = [];
        self::$archivedKeys = [];
        self::$loaded      = false;
    }

    /**
     * Devuelve el contenido hardcodeado del mensaje, sin consultar la DB.
     * Retorna null si el mensaje es dinámico (como MSG_RES_01) o si no existe.
     */
    public static function hardcodedDefault(string $id): ?string
    {
        return match($id) {
            'MSG_BIENVENIDA_CONOCIDO' => "¡Hola, {{nombre}}! Bienvenido de nuevo a El Anden 🌿\n\nSoy Andy. ¿En qué puedo ayudarte hoy?\n\n*A.* Reserva tu cancha 🏅\n*B.* Reserva tu mesa 🍽️\n*C.* Eventos / Cumpleaños 🎉\n\n*0.* Hablar con un asesor\n\nRespondé con la letra de tu elección.",
            'MSG_REGISTRO_PEDIR_NOMBRE' => "¡Hola! Bienvenido a El Anden 🌿\nSoy Andy, el asistente de reservas.\n\nPara empezar, ¿cómo te llamás?",
            'MSG_REGISTRO_CONFIRMAR_NOMBRE' => "¿Tu nombre es *{{nombre}}*?\n\nRespondé *SI* para confirmar, o escribí tu nombre correcto.",
            'MSG_REGISTRO_BIENVENIDA' => "¡Mucho gusto, {{nombre}}! Ya te registré en nuestro sistema.\n\n¿En qué puedo ayudarte hoy?\n\n*A.* Reserva tu cancha 🏅\n*B.* Reserva tu mesa 🍽️\n*C.* Eventos / Cumpleaños 🎉\n\n*0.* Hablar con un asesor\n\nRespondé con la letra de tu elección.",
            'MSG_OPCION_INVALIDA' => "No reconocí esa opción. Por favor elegí una de las opciones disponibles e intentá nuevamente.",
            'MSG_ESCALADO_HUMANO' => "Entendido. En breve un asesor de El Anden se va a comunicar con vos. 🙌\n\nMientras tanto, el asistente automático queda en pausa.\n\nSi preferís continuar sin esperar, escribí *reactivar bot* en cualquier momento.\n¡Hasta pronto! 😊",
            'MSG_TIMEOUT_ASESOR' => "¡Disculpá la demora! 🙏\n\nPasaron varias horas desde tu última consulta sin que pudiéramos terminarla. Para asegurarnos de tener tus datos al día, vamos a empezar de nuevo.\n\n¿En qué te ayudamos?",

            // Deportes
            'MSG_DEP_01' => "Contamos con canchas de Fútbol 5 y 8, Pádel y Tenis, podés ver las que quedan disponibles y reservar en https://atcsports.io/venues/el-anden-caba.\n\n💰 Para ver la *lista de precios*, respondé *ver precios*.\n\nLas canchas están disponibles de *8 a 24 hs*.\n🏢 Oficina deportiva (atención al público): *8 a 23.30 hs*.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\nEscribí *0* para hablar con un asesor, o *atrás* para volver al menú principal.",
            'MSG_DEPORTES_PRECIOS_CANCHAS' => "*PRECIOS DE CANCHAS – EL ANDÉN CLUB* 🔥\n*📍 Yerbal 1201 – Caballito*\n🥅⚽🎾🏑🙏🏻🙌🏼\n\n*🎾 PÁDEL MURO*\n\n*Lun a Vie • 7 a 16 hs*\n1h 👉 \$28.000\n1h30 👉 \$42.000\n2h 👉 \$56.000\n\n*Lun a Vie • 16 a 00 hs*\n1h 👉 \$38.000\n1h30 👉 \$57.000\n2h 👉 \$76.000\n\n*Sábados • Domingos • Feriados*\n1h 👉 \$38.000\n1h30 👉 \$57.000\n2h 👉 \$76.000\n\n*🎾 PÁDEL BLINDEX*\n\n*7 a 17 hs*\n1h 👉 \$35.000\n1h30 👉 \$52.000\n2h 👉 \$70.000\n\n*17 a 00 hs*\n1h 👉 \$47.000\n1h30 👉 \$70.000\n2h 👉 \$94.000\n\n*Sábados • Domingos • Feriados*\n1h 👉 \$47.000\n1h30 👉 \$70.000\n2h 👉 \$94.000\n\n*FÚTBOL 5 ⚽/ HOCKEY 🏑 (Arena)*\n\n*Hasta 16 hs*\nArena 👉 \$55.000 🏑\nCaucho 👉 \$70.000\n\n*Desde 16 hs*\nArena 👉 \$75.000 🏑\nCaucho 👉 \$85.000\n\n*🎾 TENIS*\nLun a Vie 8 a 16 hs 👉 \$40.000\nLun a Vie 16 a 17 hs 👉 \$75.000\nSáb/Dom 8 a 14 hs 👉 \$55.000\n\n*⚽ FÚTBOL 8*\nLun a Vie mañana 👉 \$160.000\nLun a Vie tarde/noche 👉 \$224.000\nSáb/Dom/Feriados 👉 \$224.000\n\n📲 Reservas online en los *próximos 7 días*:\nhttps://atcsports.io/venues/el-anden-caba\n\n📞 WhatsApp: *Para reservas especiales*\n11 7182-1201\n\n⚠️ *Menores de 16 años:*\nLas reservas tienen protocolos especiales y requieren supervisión del club.",

            // Restaurante — MSG_RES_01 es dinámico (fechas), no tiene default fijo
            'MSG_RES_01' => null,
            'MSG_RES_02' => "¿A qué hora querés llegar?\n\n*A.* Turno mediodía 1: 11.30 hs (hasta las 14 hs)\n*B.* Turno mediodía 2: 14 hs\n*C.* Turno mediodía completo: 12 hs (hasta las 16 hs — con menú fijo)\n*D.* Turno noche 1: 20 hs\n*E.* Turno noche 2: 22 hs\n\n*0.* Hablar con un asesor",
            'MSG_RES_HORA_PASADA' => "Ese horario ya pasó para hoy. Por favor elegí uno de los disponibles:",
            'MSG_RES_03' => "¿Para cuántas personas es la reserva?\n\n*A.* 1 a 2 personas\n*B.* 3 a 4 personas\n*C.* 5 a 6 personas\n*D.* 7 a 8 personas\n*E.* 9 a 14 personas\n*F.* 15 o más personas\n\n*0.* Hablar con un asesor",
            'MSG_RES_15PLUS' => "ℹ️ Para grupos de *15 o más personas*, la reserva requiere coordinación previa con nuestro equipo.\n\n⚠️ Los sábados y domingos al mediodía, grupos de 15 o más personas tienen *menú fijo completo obligatorio*.\n\nUn asesor se va a comunicar con vos para coordinar todos los detalles.",
            'MSG_RES_04' => "¿En qué sector preferís sentarte?\n\n*A.* Salón\n*B.* Galería\n*C.* Terraza\n*D.* Sin preferencia\n\n*0.* Hablar con un asesor",
            'MSG_RES_05' => "¿A nombre de quién reservamos la mesa?\n\n*1.* Mi nombre (uso el nombre con el que estoy registrado)\n*2.* Ingresar otro nombre\n\n*0.* Hablar con un asesor",
            'MSG_RES_05_CUSTOM' => "Por favor ingresá el nombre del responsable de la reserva:",
            'MSG_RES_06' => "¿Cuál es tu mail? Lo usamos para enviarte la confirmación y un recordatorio de tu reserva.\n\nIngresá tu dirección de correo electrónico.",
            'MSG_RES_MAIL_INVALIDO' => "El mail ingresado no parece tener un formato válido. Por favor ingresá una dirección de correo electrónico correcta (ejemplo: nombre@dominio.com).",
            'MSG_CONFIRMAR_MAIL' => "Tu mail registrado es {{mail}}.\n\n¿Es correcto?\n\nRespondé *SI* para confirmar, o ingresá uno nuevo para actualizarlo.",
            'MSG_RES_CONFIRMACION' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva (al confirmar aceptás los T&C)\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            'MSG_RES_CONFIRMACION_FUTURA' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n⚠️ Tu reserva es para una fecha fuera de nuestro período habitual de reservas (próximos 7 días). La tomamos como *pre-confirmada*, pero un asesor deberá confirmarla. Te vamos a avisar.\n\n¿Pre-confirmamos?\n\n*SI* — Pre-confirmar (al confirmar aceptás los T&C)\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            'MSG_RES_CAMBIAR' => "¿Qué dato querés cambiar?\n\n*1.* Fecha\n*2.* Horario\n*3.* Cantidad de personas\n*4.* Sector\n*5.* Nombre del responsable\n*6.* Mail\n\n*0.* Hablar con un asesor",

            // Eventos
            'MSG_EVT_01' => "¡Genial! Vamos a organizar tu cumpleaños 🎉\n\n¿Qué tipo de festejo estás planeando?\n\n*1.* Evento privado\n*2.* Fútbol (6 a 13 años)\n*3.* Pádel (hasta 16 años)\n*4.* Hockey\n*5.* Cumpleaños adolescentes (14 a 17 años)\n*6.* Cumpleaños adultos\n\n*0.* Hablar con un asesor",
            'MSG_EVT_MODALIDAD' => "¿Qué modalidad querés para el cumple?\n\n*1.* Combo Futbolero — solo fútbol\n*2.* Combo Animación Deportiva — fútbol + juegos + competencias\n\n*0.* Hablar con un asesor",
            'MSG_EVT_NOMBRE_HIJO' => "¿Cuál es el nombre del/la festejado/a y qué edad va a cumplir?\n\nEscribilo así: *Nombre, edad* (ejemplo: *Lucas, 8*)",
            'MSG_EVT_COLEGIO' => "¿En qué colegio está?",
            'MSG_EVT_NECESIDADES_ESPECIALES' => "⚠️ ¿Alguno de los chicos tiene alguna *necesidad alimentaria especial*?\n\n(celiaquía, diabetes, alergias, restricciones de dieta, etc.)\n\nSi hay alguna, describila brevemente.\nSi no hay ninguna, respondé *ninguna*.",
            'MSG_EVT_INFO_PADEL' => "🎾 *Cumpleaños Pádel — El Andén*\n\n🏟️ 1 hora de pádel por cancha (máx. 4 chicos)\n👨‍🏫 Coordinador obligatorio (menores de 16)\n🍽️ Menú incluido con bebida y servicio de mesa\n⚠️ No se permite ingresar comida, bebidas ni decoración del exterior\n\n¡Seguimos con la reserva!",
            'MSG_EVT_INFO_HOCKEY' => "🏒 *Cumpleaños Hockey — El Andén*\n\nTe recopilamos los datos para coordinar tu cumple de hockey. Un asesor va a confirmar la disponibilidad y el presupuesto final.\n\n¡Seguimos!",
            'MSG_EVT_MENU_PADEL' => "¿Qué menú preferís para los chicos?\n\n(Se elige 1 opción para todo el grupo. Incluye bebida y servicio de mesa.)\n\n*1.* Picada Andén caliente con dips\n*2.* 2 porciones de pizza\n*3.* Súper pancho\n*4.* Hamburguesa 100% carne\n\n*0.* Hablar con un asesor",
            'MSG_EVT_MENU_ADULTOS_TIPO' => "¿Qué menú completo querés para los adultos?\n\n*1.* 🥩 Parrillada Criolla Andén\n*2.* 🍖 Menú Pernil\n*3.* 🍗 Menú Milanesa Completa Club\n*4.* 🍕 Menú Pizza Andén\n\n*0.* Hablar con un asesor",
            'MSG_EVT_NINOS_PACK' => "¡Nos encanta que elijan festejar en El Andén! 🎉\n\nReservamos tu cancha de Fútbol 5 u 8. Hasta 20 nenes por cancha, duración máxima 2 hs y 15 min con intermedio para comer, 2 coordinadores, menú fijo + extras adicionales (no incluidos). La comida, canchas y coordinadores son obligatorios y proporcionales a la cantidad de niños.",
            'MSG_EVT_02' => "¿Para qué fecha es el evento?\n\nIngresá la fecha (ejemplos válidos: *15/08/26*, *15-08-26*, *15/08*, *15-08*).\nLa fecha tiene que ser posterior al día de hoy.\n\nEscribí *0* para hablar con un asesor.",
            'MSG_EVT_FECHA_PASADA' => "Esa fecha ya pasó. Por favor ingresá una fecha posterior al día de hoy.",
            'MSG_EVT_FERIADO_AVISO' => "⚠️ *La fecha elegida es feriado nacional.*\n\nSe aplicará un recargo del *30%* sobre el costo total del evento. El presupuesto final ya incluye este recargo.",
            'MSG_EVT_03_ENTERO' => "¿A qué hora comienza el evento?\n\nHorario disponible: *{{rango_horario}}*\nFormatos válidos: *20*, *20:00*, *20.00*, *20hs*, *8pm*.\n\nEscribí *0* para hablar con un asesor.",
            'MSG_EVT_03_HHMM' => "¿A qué hora comienza el evento?\n\nIngresá la hora de inicio.\nFormatos válidos: *20:00*, *20.00*, *20hs*, *8pm*.\n\nEscribí *0* para hablar con un asesor.",
            'MSG_EVT_CAMBIAR' => "¿Qué dato querés cambiar?\n\n*1.* Fecha\n*2.* Hora de inicio\n*3.* Cantidad de personas\n*4.* Nombre del responsable\n*5.* Mail\n\n*0.* Hablar con un asesor",
            'MSG_EVT_NINOS_CAMBIAR' => "¿Qué dato querés cambiar?\n\n*1.* Fecha\n*2.* Hora de inicio\n*3.* Nombre del/la festejado/a\n*4.* Nombre del responsable\n*5.* Mail\n\n*0.* Hablar con un asesor",
            'MSG_EVT_05' => "¿Cuántos niños van a participar?\n\nIngresá un número entre 1 y 50.\nSi son más de 50, escribí *0* para hablar con un asesor.",
            'MSG_EVT_COSTO_MENU' => "Para {{numero_ninos}} niños, el costo estimado del menú es de \${{costo_menu_calculado}}. 🧮\n\nA continuación te hacemos algunas preguntas más para completar tu presupuesto.",
            'MSG_EVT_MENU' => "¿Qué menú preferís para los chicos?\n\n*1.* 2 porciones de pizza\n*2.* Pancho\n*3.* Hamburguesa\n\n*0.* Hablar con un asesor",
            'MSG_EVT_ADULTOS' => "¿Cuántos adultos van a asistir al evento?\n\nIngresá un número (puede ser 0).\n\nℹ️ Hasta 10 adultos al almuerzo/cena: eligen de la carta el día del evento.\n   Más de 10 adultos: menú fijo completo obligatorio.\n   Merienda (16–19:30 hs): hasta 15 adultos a elección.",
            'MSG_EVT_MENU_ADULTOS' => "De los {{numero_adultos}} adultos, ¿cuántos van a tomar el menú fijo?\n\nIngresá un número entre 0 y {{numero_adultos}}.",
            'MSG_EVT_ADICIONALES' => "¿Querés agregar alimentos adicionales al evento? (no incluidos en el pack base)\n\n*1.* 🍟 Bandejas de Papas Fritas Calientes\n*2.* 🥪 Sándwiches de Miga\n*3.* 🍉 Bandejas de Frutas\n*4.* 🍦 Helados (Palito de Agua o Bombón Helado)\n\nPodés elegir varias opciones separando los números con coma (ejemplo: *1,3*).\nSi no querés adicionales, respondé *ninguno*.\n\n*0.* Hablar con un asesor",
            'MSG_EVT_ADICIONAL_QTY' => "¿Cuántas {{item_name}} querés agregar?\n\nIngresá un número entero.",
            'MSG_EVT_EXTRAS' => "¿Hay algo especial que quieras agregar o consultar para el evento?\n\nPodés escribirlo libremente. Un asesor lo va a revisar y confirmar.\nSi no tenés extras, respondé *ninguno*.",
            'MSG_EVT_MAIL' => "¿Cuál es tu mail de contacto? Lo usamos para enviarte la confirmación del evento.\n\nIngresá tu dirección de correo, o escribí *no* para omitir.",
            'MSG_EVT_07' => "¿A nombre de quién registramos el evento?\n\n*1.* Mi nombre (uso el nombre con el que estoy registrado)\n*2.* Ingresar otro nombre\n\n*0.* Hablar con un asesor",
            'MSG_EVT_07_CUSTOM' => "Por favor ingresá el nombre del responsable del evento:",
            'MSG_EVT_PERSONAS' => "¿Cuántas personas van a asistir?\n\nIngresá un número entero (1 a 999).\n\nEscribí *0* para hablar con un asesor.",
            'MSG_EVT_PERSONAS_AVISO_MENU' => "ℹ️ Para eventos de *más de 15 personas* contamos con opciones de menú especiales (menú fijo completo). Un asesor te va a detallar las opciones disponibles una vez que confirmemos tu reserva.",
            'MSG_CONFIRMACION' => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva (al confirmar aceptás los T&C)\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            'MSG_RES_FIN_DE_SEMANA'        => "🗓️ *¡Atención — Sábado, Domingo o Feriado!*\n\nSi vas a reservar al mediodía, te avisamos que ese turno es *completo y obligatorio*, con horario fijo de *12:00 a 16:00 hs*.",
            'MSG_RES_FINDE_TURNOS'         => "¿A qué turno querés asistir?\n\n*A.* 12:00 hs\n*B.* 14:00 hs\n\n*0.* Hablar con un asesor",
            'MSG_RES_FINDE_ORDEN_LLEGADA'  => "🗓️ Los sábados, domingos y feriados las reservas se toman hasta las *11:00 hs*.\n\nPasado ese horario, la atención es *por orden de llegada*. ¡Te esperamos! 🌿",
            'MSG_RES_SECTOR_LLENO'         => "⚠️ En este momento todos los sectores están completos para esa fecha.\n\nUn asesor se va a comunicar con vos para ver si podemos encontrarte lugar. 🙏",
            'MSG_LINK_CUMPLE_PADEL' => "🎾 *Packs de Cumpleaños Pádel — Opciones y Precios:*\n_(link próximamente)_",
            'MSG_LINK_CUMPLE_HOCKEY' => "🏒 *Packs de Cumpleaños Hockey — Opciones y Precios:*\n_(link próximamente)_",
            'MSG_LINK_TYC' => "📄 *Términos y Condiciones de El Andén:*\nhttps://drive.google.com/file/d/14djnk1Lp5-zvc33UeIbDDmTBcXr5ub3t/view?usp=sharing\n\nPor favor, leelo antes de confirmar tu reserva.",
            'MSG_LINK_CUMPLE_NINOS' => "🎉 *Packs de Cumpleaños Niños — Opciones y Precios:*\nhttps://drive.google.com/file/d/1E-WP63zeEupvzXJJQv7-0337prMjena2/view?usp=drive_link",
            'MSG_LINK_CUMPLE_ADOLESCENTES' => "🎉 *Packs de Cumpleaños Adolescentes — Opciones:*\nhttps://drive.google.com/file/d/1pKLIUYpNucTk8aA7XfXqSdiu-zzWmz_z/view?usp=sharing",
            'MSG_RESERVA_EXITOSA' => "✅ ¡Tu reserva está confirmada!\n\nGuardamos todos los datos. Si necesitás hacer algún cambio o tenés alguna consulta, no dudes en escribirnos.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\n¡Hasta pronto en El Anden! 🌿",
            'MSG_RESERVA_PRECONFIRMADA' => "✅ ¡Tu pre-reserva fue registrada!\n\nUn asesor de El Anden se va a comunicar con vos para confirmarla.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\n¡Hasta pronto! 🌿",
            'MSG_VOLVER_CONFIRMADA' => "Tu reserva ya fue confirmada, por lo que no es posible modificarla desde acá.\n\nSi necesitás hacer un cambio o cancelación, un asesor de El Anden puede ayudarte.\n\nEscribí *0* para hablar con un asesor.",
            'MSG_DESPEDIDA' => "¡Hasta pronto! 👋\n\nSi necesitás algo, escribinos cuando quieras. En El Anden siempre vas a tener un lugar 🌿",
            default => null,
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
            '/^\*?([A-Za-z0-9]+)\.\*?[ \t]*(.+)/m',
            $rendered,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $m) {
            $options[strtoupper(trim($m[1]))] = trim($m[2]);
        }
        return $options;
    }

    /** Devuelve el label de la opción o null si no existe. Acepta tanto clave como label (case-insensitive). */
    public static function resolveOption(string $id, string $input, array $vars = []): ?string
    {
        $opts  = self::parseOptions($id, $vars);
        $upper = strtoupper(trim($input));

        if (isset($opts[$upper])) return $opts[$upper];

        // Label match (case-insensitive)
        $normalInput = mb_strtolower(trim($input));
        foreach ($opts as $label) {
            if (mb_strtolower($label) === $normalInput) return $label;
        }

        return null;
    }

    /**
     * Devuelve la CLAVE de la opción coincidente, buscando tanto por clave como por label (case-insensitive).
     * Útil cuando se necesita guardar la clave, no el label.
     */
    public static function findOptionKey(string $id, string $input, array $vars = []): ?string
    {
        $opts  = self::parseOptions($id, $vars);
        $upper = strtoupper(trim($input));

        if (isset($opts[$upper])) return $upper;

        $normalInput = mb_strtolower(trim($input));
        foreach ($opts as $key => $label) {
            if (mb_strtolower($label) === $normalInput) return $key;
        }

        return null;
    }

    public static function resolveFechaRestaurante(string $opcion): ?string
    {
        $idx = (int) $opcion - 1;
        if ($idx < 0) return null;
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
        return match(strtoupper(trim($opcion))) {
            'A', 'SALON', 'SALÓN' => 'Salón',
            'B', 'GALERIA', 'GALERÍA' => 'Galería',
            'C', 'TERRAZA' => 'Terraza',
            'D', 'PARRILLA' => 'Parrilla',
            'E', 'PATIO' => 'Patio',
            'F', 'SIN PREFERENCIA' => 'Sin preferencia',
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
            '2' => 'Cumpleaños fútbol (6 a 13 años)',
            '3' => 'Cumpleaños pádel (hasta 16 años)',
            '4' => 'Cumpleaños hockey',
            '5' => 'Cumpleaños adolescentes (14 a 17 años)',
            '6' => 'Cumpleaños adultos',
            default => 'Tipo ' . $opcion,
        };
    }

    public static function modalidadLabel(string $opcion): string
    {
        return match($opcion) {
            '1' => 'Combo Futbolero',
            '2' => 'Combo Animación Deportiva',
            default => 'Modalidad ' . $opcion,
        };
    }

    public static function menuPadelLabel(string $opcion): string
    {
        return match($opcion) {
            '1' => 'Picada Andén caliente con dips',
            '2' => '2 porciones de pizza',
            '3' => 'Súper pancho',
            '4' => 'Hamburguesa 100% carne',
            default => 'Opción ' . $opcion,
        };
    }

    public static function menuAdultosLabel(string $opcion): string
    {
        return match($opcion) {
            '1' => 'Parrillada Criolla Andén',
            '2' => 'Menú Pernil',
            '3' => 'Menú Milanesa Completa Club',
            '4' => 'Menú Pizza Andén',
            default => 'Opción ' . $opcion,
        };
    }

    public static function menuAdultosConcepto(string $opcion): string
    {
        return match($opcion) {
            '1' => 'menu_adulto_parrillada',
            '2' => 'menu_adulto_pernil',
            '3' => 'menu_adulto_milanesa',
            '4' => 'menu_adulto_pizza',
            default => 'menu_adulto_parrillada',
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
