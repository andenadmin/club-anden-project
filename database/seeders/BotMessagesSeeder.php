<?php

namespace Database\Seeders;

use App\Models\BotMessage;
use Illuminate\Database\Seeder;

class BotMessagesSeeder extends Seeder
{
    public function run(): void
    {
        $messages = [
            // ─── General ─────────────────────────────────────────────────────
            [
                'key'      => 'MSG_BIENVENIDA_CONOCIDO',
                'category' => 'general',
                'label'    => 'Bienvenida (cliente conocido)',
                'content'  => "¡Hola, {{nombre}}! Bienvenido de nuevo a El Anden 🌿\n\nSoy Andy. ¿En qué puedo ayudarte hoy?\n\n*A.* Reserva tu cancha 🏅\n*B.* Reserva tu mesa 🍽️\n*C.* Eventos / Cumpleaños 🎉\n\n*0.* Hablar con un asesor\n\nRespondé con la letra de tu elección.",
            ],
            [
                'key'      => 'MSG_REGISTRO_PEDIR_NOMBRE',
                'category' => 'general',
                'label'    => 'Registro — pedir nombre',
                'content'  => "¡Hola! Bienvenido a El Anden 🌿\nSoy Andy, el asistente de reservas.\n\nPara empezar, ¿cómo te llamás?",
            ],
            [
                'key'      => 'MSG_REGISTRO_BIENVENIDA',
                'category' => 'general',
                'label'    => 'Registro — bienvenida tras registro',
                'content'  => "¡Mucho gusto, {{nombre}}! Ya te registré en nuestro sistema.\n\n¿En qué puedo ayudarte hoy?\n\n*A.* Reserva tu cancha 🏅\n*B.* Reserva tu mesa 🍽️\n*C.* Eventos / Cumpleaños 🎉\n\n*0.* Hablar con un asesor\n\nRespondé con la letra de tu elección.",
            ],
            [
                'key'      => 'MSG_OPCION_INVALIDA',
                'category' => 'general',
                'label'    => 'Opción inválida',
                'content'  => "No reconocí esa opción. Por favor elegí una de las opciones disponibles e intentá nuevamente.",
            ],
            [
                'key'      => 'MSG_ESCALADO_HUMANO',
                'category' => 'general',
                'label'    => 'Escalado a asesor humano',
                'content'  => "Entendido. En breve un asesor de El Anden se va a comunicar con vos.\n\nMientras tanto, el asistente automático queda en pausa.\n¡Hasta pronto! 😊",
            ],
            [
                'key'      => 'MSG_CONFIRMACION',
                'category' => 'general',
                'label'    => 'Confirmación de reserva (genérico)',
                'content'  => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            ],
            [
                'key'      => 'MSG_RESERVA_EXITOSA',
                'category' => 'general',
                'label'    => 'Reserva confirmada',
                'content'  => "✅ ¡Tu reserva está confirmada!\n\nGuardamos todos los datos. Si necesitás hacer algún cambio o tenés alguna consulta, no dudes en escribirnos.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\n¡Hasta pronto en El Anden! 🌿",
            ],
            [
                'key'      => 'MSG_RESERVA_PRECONFIRMADA',
                'category' => 'general',
                'label'    => 'Pre-reserva registrada',
                'content'  => "✅ ¡Tu pre-reserva fue registrada!\n\nUn asesor de El Anden se va a comunicar con vos para confirmarla.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\n¡Hasta pronto! 🌿",
            ],

            // ─── Deportes ────────────────────────────────────────────────────
            [
                'key'      => 'MSG_DEP_01',
                'category' => 'deportes',
                'label'    => 'Información de canchas',
                'content'  => "Contamos con canchas de Fútbol 5 y 8, Pádel y Tenis, podés ver las que quedan disponibles y reservar en https://atcsports.io/venues/el-anden-caba.\n\nLas canchas están disponibles de *8 a 24 hs*.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\nSi necesitás algo más por fuera de la página, escribí \"hablar con un asesor\".",
            ],

            // ─── Restaurante ─────────────────────────────────────────────────
            [
                'key'      => 'MSG_RES_02',
                'category' => 'restaurante',
                'label'    => 'Restaurante — elegir horario',
                'content'  => "¿A qué hora querés llegar?\n\n*1.* Turno 1: 12.30 hs\n*2.* Turno 2: 14 hs\n*3.* Turno 3: 20 hs\n*4.* Turno 4: 22 hs\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_RES_03',
                'category' => 'restaurante',
                'label'    => 'Restaurante — cantidad de personas',
                'content'  => "¿Para cuántas personas es la reserva?\n\n*1.* 1 a 2 personas\n*2.* 3 a 4 personas\n*3.* 5 a 6 personas\n*4.* 7 a 8 personas\n*5.* Más de 8 personas\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_RES_04',
                'category' => 'restaurante',
                'label'    => 'Restaurante — preferencia de sector',
                'content'  => "¿Tenés preferencia de sector?\n\n*1.* Interior\n*2.* Exterior\n*3.* Sin preferencia\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_RES_05',
                'category' => 'restaurante',
                'label'    => 'Restaurante — nombre del responsable',
                'content'  => "¿A nombre de quién reservamos la mesa?\n\n*1.* Mi nombre (uso el nombre con el que estoy registrado)\n*2.* Ingresar otro nombre\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_RES_05_CUSTOM',
                'category' => 'restaurante',
                'label'    => 'Restaurante — ingresar nombre custom',
                'content'  => "Por favor ingresá el nombre del responsable de la reserva:",
            ],
            [
                'key'      => 'MSG_RES_06',
                'category' => 'restaurante',
                'label'    => 'Restaurante — pedir mail',
                'content'  => "¿Cuál es tu mail? Lo usamos para enviarte la confirmación y un recordatorio de tu reserva.\n\nIngresá tu dirección de correo electrónico.",
            ],
            [
                'key'      => 'MSG_RES_MAIL_INVALIDO',
                'category' => 'restaurante',
                'label'    => 'Restaurante — mail inválido',
                'content'  => "El mail ingresado no parece tener un formato válido. Por favor ingresá una dirección de correo electrónico correcta (ejemplo: nombre@dominio.com).",
            ],
            [
                'key'      => 'MSG_RES_CONFIRMACION',
                'category' => 'restaurante',
                'label'    => 'Restaurante — confirmación de reserva',
                'content'  => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n¿Confirmamos?\n\n*SI* — Confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            ],
            [
                'key'      => 'MSG_RES_CONFIRMACION_FUTURA',
                'category' => 'restaurante',
                'label'    => 'Restaurante — confirmación fecha futura',
                'content'  => "Perfecto, revisá el resumen de tu reserva:\n\n{{resumen}}\n\n⚠️ Tu reserva es para una fecha fuera de nuestro período habitual de reservas (próximos 7 días). La tomamos como *pre-confirmada*, pero un asesor deberá confirmarla. Te vamos a avisar.\n\n¿Pre-confirmamos?\n\n*SI* — Pre-confirmar reserva\n*CAMBIAR* — Modificar un dato\n\n*0.* Hablar con un asesor (para cancelar u otras consultas)",
            ],
            [
                'key'      => 'MSG_RES_CAMBIAR',
                'category' => 'restaurante',
                'label'    => 'Restaurante — qué dato cambiar',
                'content'  => "¿Qué dato querés cambiar?\n\n*1.* Fecha\n*2.* Horario\n*3.* Cantidad de personas\n*4.* Sector\n*5.* Nombre del responsable\n*6.* Mail\n\n*0.* Hablar con un asesor",
            ],

            // ─── Eventos ─────────────────────────────────────────────────────
            [
                'key'      => 'MSG_EVT_01',
                'category' => 'eventos',
                'label'    => 'Eventos — tipo de evento',
                'content'  => "¡Genial! Vamos a organizar tu evento 🎉\n\n¿Qué tipo de evento estás planeando?\n\n*1.* Evento privado (te contactamos con un asesor)\n*2.* Cumpleaños niños (6 a 12 años)\n*3.* Cumpleaños adolescentes (13 a 17 años)\n*4.* Cumpleaños adultos\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_NINOS_PACK',
                'category' => 'eventos',
                'label'    => 'Eventos — pack niños',
                'content'  => "¡Nos encanta que elijan festejar en El Anden! 🎉\n\nEn el pack reservamos tu cancha de Fútbol 5 u 8. Hasta 20 nenes por cancha, duración máxima 2 hs y 15 min con intermedio para comer, 2 coordinadores, menú fijo + extras adicionales (no incluidos). La comida, canchas y coordinadores son obligatorios y proporcionales a la cantidad de niños.\n\nPara ver el detalle de las opciones con precios estimados: [LINK_MENU_PACKS]\n\n¿Qué opción elegís?\n\n*1.* Pack 1\n*2.* Pack 2\n*3.* Pack 3\n*4.* Pack 4\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_02',
                'category' => 'eventos',
                'label'    => 'Eventos — fecha del evento',
                'content'  => "¿Para qué fecha es el evento?\n\nIngresá la fecha en formato DD/MM/AA (ejemplo: 15/08/26).\n\nEscribí *0* para hablar con un asesor.",
            ],
            [
                'key'      => 'MSG_EVT_FERIADO_AVISO',
                'category' => 'eventos',
                'label'    => 'Eventos — aviso fecha feriado',
                'content'  => "⚠️ *La fecha elegida es feriado nacional.*\n\nSe aplicará un recargo del *30%* sobre el costo total del evento. El presupuesto final ya incluye este recargo.",
            ],
            [
                'key'      => 'MSG_EVT_03_ENTERO',
                'category' => 'eventos',
                'label'    => 'Eventos — hora (número entero)',
                'content'  => "¿A qué hora comienza el evento?\n\nIngresá la hora de inicio como número entero entre 8 y 23 (ejemplo: 20).\n\nEscribí *0* para hablar con un asesor.",
            ],
            [
                'key'      => 'MSG_EVT_03_HHMM',
                'category' => 'eventos',
                'label'    => 'Eventos — hora (formato HH:MM)',
                'content'  => "¿A qué hora comienza el evento?\n\nIngresá la hora en formato HH:MM en 24 hs (ejemplo: 20:00).\n\nEscribí *0* para hablar con un asesor.",
            ],
            [
                'key'      => 'MSG_EVT_05',
                'category' => 'eventos',
                'label'    => 'Eventos — cantidad de niños',
                'content'  => "¿Cuántos niños van a participar?\n\nIngresá un número entre 1 y 50.\nSi son más de 50, escribí *0* para hablar con un asesor.",
            ],
            [
                'key'      => 'MSG_EVT_COSTO_MENU',
                'category' => 'eventos',
                'label'    => 'Eventos — costo estimado del menú',
                'content'  => "Para {{numero_ninos}} niños con {{pack_label}}, el costo estimado del menú es de \${{costo_menu_calculado}}. 🧮\n\nA continuación te hacemos algunas preguntas más para completar tu presupuesto.",
            ],
            [
                'key'      => 'MSG_EVT_MENU',
                'category' => 'eventos',
                'label'    => 'Eventos — menú niños',
                'content'  => "¿Qué menú preferís para los chicos?\n\n*1.* 2 porciones de pizza\n*2.* Pancho\n*3.* Hamburguesa\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_ADULTOS',
                'category' => 'eventos',
                'label'    => 'Eventos — cantidad de adultos',
                'content'  => "¿Cuántos adultos van a asistir al evento?\n\nIngresá un número (puede ser 0).\n\nℹ️ Los adultos pueden optar por:\n• Menú fijo: \${{precio_menu_adulto}} por persona (se suma al presupuesto)\n• Cafetería libre: disponible en el lugar, no incluida en el pack",
            ],
            [
                'key'      => 'MSG_EVT_MENU_ADULTOS',
                'category' => 'eventos',
                'label'    => 'Eventos — menú adultos cantidad',
                'content'  => "De los {{numero_adultos}} adultos, ¿cuántos van a tomar el menú fijo?\n\nIngresá un número entre 0 y {{numero_adultos}}.",
            ],
            [
                'key'      => 'MSG_EVT_ADICIONALES',
                'category' => 'eventos',
                'label'    => 'Eventos — adicionales',
                'content'  => "¿Querés agregar alimentos adicionales al evento? (no incluidos en el pack base)\n\n*1.* 🍟 Bandejas de Papas Fritas Calientes\n*2.* 🥪 Sándwiches de Miga\n*3.* 🍉 Bandejas de Frutas\n*4.* 🍦 Helados (Palito de Agua o Bombón Helado)\n\nPodés elegir varias opciones separando los números con coma (ejemplo: *1,3*).\nSi no querés adicionales, respondé *ninguno*.\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_ADICIONAL_QTY',
                'category' => 'eventos',
                'label'    => 'Eventos — cantidad de adicional',
                'content'  => "¿Cuántas {{item_name}} querés agregar?\n\nIngresá un número entero.",
            ],
            [
                'key'      => 'MSG_EVT_EXTRAS',
                'category' => 'eventos',
                'label'    => 'Eventos — extras libres',
                'content'  => "¿Hay algo especial que quieras agregar o consultar para el evento?\n\nPodés escribirlo libremente. Un asesor lo va a revisar y confirmar.\nSi no tenés extras, respondé *ninguno*.",
            ],
            [
                'key'      => 'MSG_EVT_MAIL',
                'category' => 'eventos',
                'label'    => 'Eventos — mail de contacto',
                'content'  => "¿Cuál es tu mail de contacto? Lo usamos para enviarte la confirmación del evento.\n\nIngresá tu dirección de correo, o escribí *no* para omitir.",
            ],
            [
                'key'      => 'MSG_EVT_07',
                'category' => 'eventos',
                'label'    => 'Eventos — nombre del responsable',
                'content'  => "¿A nombre de quién registramos el evento?\n\n*1.* Mi nombre (uso el nombre con el que estoy registrado)\n*2.* Ingresar otro nombre\n\n*0.* Hablar con un asesor",
            ],
            [
                'key'      => 'MSG_EVT_07_CUSTOM',
                'category' => 'eventos',
                'label'    => 'Eventos — ingresar nombre custom',
                'content'  => "Por favor ingresá el nombre del responsable del evento:",
            ],
            [
                'key'      => 'MSG_EVT_PERSONAS',
                'category' => 'eventos',
                'label'    => 'Eventos — cantidad de personas',
                'content'  => "¿Cuántas personas van a asistir?\n\nIngresá un número entero (1 a 999).\n\nEscribí *0* para hablar con un asesor.",
            ],
        ];

        foreach ($messages as $msg) {
            BotMessage::updateOrCreate(['key' => $msg['key']], $msg);
        }
    }
}
