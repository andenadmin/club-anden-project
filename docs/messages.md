# MESSAGES.MD — BOT EL ANDEN
# SOURCE OF TRUTH — NO MODIFICAR SIN AUTORIZACIÓN
# TEXTOS EXACTOS. NO REESCRIBIR. NO RESUMIR. NO OPTIMIZAR.

---

## CONVENCIONES

- `{{nombre}}` = variable: nombre del cliente
- `{{rama}}` = variable: nombre de la rama de servicio
- `{{resumen}}` = variable: bloque de resumen de atributos
- Los separadores de línea dentro de un mensaje se indican con `\n`

---

## MSG_BIENVENIDA_CONOCIDO
**ID:** MSG_BIENVENIDA_CONOCIDO
**Estado:** INICIO → MENU_PRINCIPAL — cliente registrado con nombre
**Nota:** {{nombre}} = nombre del cliente recuperado de tabla_clientes

```
¡Hola, {{nombre}}! Bienvenido de nuevo a El Anden 🌿\n
Soy Andy. ¿En qué puedo ayudarte hoy?\n
\n
*A.* Reserva tu cancha 🏅\n
*B.* Reserva tu mesa 🍽️\n
*C.* Eventos / Cumpleaños 🎉\n
\n
*0.* Hablar con un asesor\n
\n
Respondé con la letra de tu elección.
```

---

## MSG_REGISTRO_PEDIR_NOMBRE
**ID:** MSG_REGISTRO_PEDIR_NOMBRE
**Estado:** INICIO → REGISTRO_CLIENTE — cliente no registrado o sin nombre

```
¡Hola! Bienvenido a El Anden 🌿\n
Soy Andy, el asistente de reservas.\n
\n
Para empezar, ¿cómo te llamás?
```

---

## MSG_REGISTRO_BIENVENIDA
**ID:** MSG_REGISTRO_BIENVENIDA
**Estado:** REGISTRO_CLIENTE → MENU_PRINCIPAL — tras recibir el nombre
**Nota:** {{nombre}} = nombre ingresado por el cliente

```
¡Mucho gusto, {{nombre}}! Ya te registré en nuestro sistema.\n
\n
¿En qué puedo ayudarte hoy?\n
\n
*A.* Reserva tu cancha 🏅\n
*B.* Reserva tu mesa 🍽️\n
*C.* Eventos / Cumpleaños 🎉\n
\n
*0.* Hablar con un asesor\n
\n
Respondé con la letra de tu elección.
```

---

## MSG_BIENVENIDA
**ID:** MSG_BIENVENIDA
**Estado:** INICIO → MENU_PRINCIPAL

```
¡Hola! Bienvenido a El Anden \n
Soy Andy, el asistente de reservas. ¿En qué puedo ayudarte hoy?\n
\n
*A.* Reserva tu cancha 🏅\n
*B.* Reserva tu mesa 🍽️\n
*C.* Eventos / Cumpleaños 🎉\n
\n
*0.* Hablar con un asesor\n
\n
Respondé con la letra de tu elección.
```

---

## MSG_OPCION_INVALIDA
**ID:** MSG_OPCION_INVALIDA
**Estado:** Cualquier paso ante respuesta no válida (primer intento)

```
No reconocí esa opción. Por favor elegí una de las opciones disponibles e intentá nuevamente.
```

---

## MSG_ESCALADO_HUMANO
**ID:** MSG_ESCALADO_HUMANO
**Estado:** ESCALADO_HUMANO

```
Entendido. En breve un asesor de El Anden se va a comunicar con vos.\n
\n
Mientras tanto, el asistente automático queda en pausa.\n
¡Hasta pronto! 😊
```

---

## RAMA DEPORTES — MSG_DEP_01
**ID:** MSG_DEP_01
**Paso:** link_reserva
**Objetivo:** derivar al link de reserva e informar horarios y acceso al club

```
Contamos con canchas de Fútbol 5 y 8, Pádel y Tenis, podés ver las que quedan disponibles y reservar en https://atcsports.io/venues/el-anden-caba.\n
\n
Las canchas están disponibles de *8 a 24 hs*.\n
\n
📍 *Cómo llegar:*\n
• Estacionamiento gratuito: Yerbal 1201\n
• Entrada peatonal: Yerbal 1255\n
\n
Si necesitás algo más por fuera de la página, escribí "hablar con un asesor".
```

---

## RAMA RESTAURANTE — MSG_RES_01
**ID:** MSG_RES_01
**Paso:** fecha
**Objetivo:** Fecha de la reserva en restaurante
**Nota:** Las opciones se generan dinámicamente con la fecha real en formato DD/MM/AA + etiqueta relativa. Ejemplo de renderizado:

```
¡Perfecto! Vamos a reservar tu mesa 🍽️\n
\n
¿Para qué fecha querés reservar?\n
\n
*1.* 28/04/26 (Hoy)\n
*2.* 29/04/26 (Mañana)\n
*3.* 30/04/26 (En 2 días)\n
*4.* 01/05/26 (En 3 días)\n
*5.* 02/05/26 (En 4 días)\n
*6.* 03/05/26 (En 5 días)\n
*7.* 04/05/26 (En 6 días)\n
\n
*0.* Hablar con un asesor
```

---

## RAMA RESTAURANTE — MSG_RES_02
**ID:** MSG_RES_02
**Paso:** hora
**Objetivo:** Horario de la reserva

```
¿A qué hora querés llegar?\n
\n
*1.* Turno 1: 12.30 hs\n
*2.* Turno 2: 14 hs\n
*3.* Turno 3: 20 hs\n
*4.* Turno 4: 22 hs\n
\n
*0.* Hablar con un asesor
```

---

## RAMA RESTAURANTE — MSG_RES_03
**ID:** MSG_RES_03
**Paso:** numero_personas
**Objetivo:** Cantidad de comensales

```
¿Para cuántas personas es la reserva?\n
\n
*1.* 1 a 2 personas\n
*2.* 3 a 4 personas\n
*3.* 5 a 6 personas\n
*4.* 7 a 8 personas\n
*5.* Más de 8 personas\n
\n
*0.* Hablar con un asesor
```

---

## RAMA RESTAURANTE — MSG_RES_04
**ID:** MSG_RES_04
**Paso:** sector
**Objetivo:** Preferencia de sector dentro del restaurante

```
¿Tenés preferencia de sector?\n
\n
*1.* Interior\n
*2.* Exterior\n
*3.* Sin preferencia\n
\n
*0.* Hablar con un asesor
```

---

## RAMA RESTAURANTE — MSG_RES_05
**ID:** MSG_RES_05
**Paso:** nombre_responsable
**Objetivo:** Nombre del responsable de la reserva

```
¿A nombre de quién reservamos la mesa?\n
\n
*1.* Mi nombre (uso el nombre con el que estoy registrado)\n
*2.* Ingresar otro nombre\n
\n
*0.* Hablar con un asesor
```

---

## RAMA RESTAURANTE — MSG_RES_06
**ID:** MSG_RES_06
**Paso:** mail_contacto
**Objetivo:** Solicitar mail para enviar recordatorio por Google Calendar

```
¿Cuál es tu mail? Lo usamos para enviarte la confirmación y un recordatorio de tu reserva.\n
\n
Ingresá tu dirección de correo electrónico.
```

---

## RAMA RESTAURANTE — MSG_RES_MAIL_INVALIDO
**ID:** MSG_RES_MAIL_INVALIDO
**Estado:** Ante mail con formato inválido (primer intento)

```
El mail ingresado no parece tener un formato válido. Por favor ingresá una dirección de correo electrónico correcta (ejemplo: nombre@dominio.com).
```

---

## RAMA RESTAURANTE — MSG_RES_CONFIRMACION
**ID:** MSG_RES_CONFIRMACION
**Estado:** CONFIRMACION rama RESTAURANTE — fecha dentro de los próximos 7 días
**Nota:** {{resumen}} es el bloque generado dinámicamente con los datos recolectados

```
Perfecto, revisá el resumen de tu reserva:\n
\n
{{resumen}}\n
\n
¿Confirmamos?\n
\n
*SI* — Confirmar reserva\n
*CAMBIAR* — Modificar un dato\n
\n
*0.* Hablar con un asesor (para cancelar u otras consultas)
```

---

## RAMA RESTAURANTE — MSG_RES_CONFIRMACION_FUTURA
**ID:** MSG_RES_CONFIRMACION_FUTURA
**Estado:** CONFIRMACION rama RESTAURANTE — fecha posterior a los próximos 7 días
**Nota:** {{resumen}} es el bloque generado dinámicamente con los datos recolectados

```
Perfecto, revisá el resumen de tu reserva:\n
\n
{{resumen}}\n
\n
⚠️ Tu reserva es para una fecha fuera de nuestro período habitual de reservas (próximos 7 días). La tomamos como *pre-confirmada*, pero un asesor deberá confirmarla. Te vamos a avisar.\n
\n
¿Pre-confirmamos?\n
\n
*SI* — Pre-confirmar reserva\n
*CAMBIAR* — Modificar un dato\n
\n
*0.* Hablar con un asesor (para cancelar u otras consultas)
```

---

## RAMA RESTAURANTE — MSG_RES_CAMBIAR
**ID:** MSG_RES_CAMBIAR
**Estado:** Cliente solicita cambiar un dato en CONFIRMACION

```
¿Qué dato querés cambiar?\n
\n
*1.* Fecha\n
*2.* Horario\n
*3.* Cantidad de personas\n
*4.* Sector\n
*5.* Nombre del responsable\n
*6.* Mail\n
\n
*0.* Hablar con un asesor
```

---

## RAMA EVENTOS — MSG_EVT_01
**ID:** MSG_EVT_01
**Paso:** tipo_evento
**Objetivo:** Identificar el tipo de evento

```
¡Genial! Vamos a organizar tu evento 🎉\n
\n
¿Qué tipo de evento estás planeando?\n
\n
*1.* Evento privado (te contactamos con un asesor)\n
*2.* Cumpleaños niños (6 a 12 años)\n
*3.* Cumpleaños adolescentes (13 a 17 años)\n
*4.* Cumpleaños adultos\n
\n
*0.* Hablar con un asesor
```

---

## RAMA EVENTOS — MSG_EVT_NINOS_PACK
**ID:** MSG_EVT_NINOS_PACK
**Paso:** pack_seleccionado
**Objetivo:** Selección de pack para cumpleaños niños (6 a 12 años)
**Aplica a:** subtipo NINOS

```
¡Nos encanta que elijan festejar en El Anden! 🎉\n
\n
En el pack reservamos tu cancha de Fútbol 5 u 8. Hasta 20 nenes por cancha, duración máxima 2 hs y 15 min con intermedio para comer, 2 coordinadores, menú fijo + extras adicionales (no incluidos). La comida, canchas y coordinadores son obligatorios y proporcionales a la cantidad de niños.\n
\n
Para ver el detalle de las opciones con precios estimados: [LINK_MENU_PACKS]\n
\n
¿Qué opción elegís?\n
\n
*1.* Pack 1\n
*2.* Pack 2\n
*3.* Pack 3\n
*4.* Pack 4\n
\n
*0.* Hablar con un asesor
```

---

## RAMA EVENTOS — MSG_EVT_02
**ID:** MSG_EVT_02
**Paso:** fecha
**Objetivo:** Fecha del evento

```
¿Para qué fecha es el evento?\n
\n
Ingresá la fecha en formato DD/MM/AA (ejemplo: 15/08/26).\n
\n
Escribí *0* para hablar con un asesor.
```

---

## RAMA EVENTOS — MSG_EVT_03
**ID:** MSG_EVT_03
**Paso:** hora_inicio
**Objetivo:** Horario de inicio del evento
**Formato entrada:** número entero entre 8 y 23 (club abierto de 8 a 24 hs)

```
¿A qué hora comienza el evento?\n
\n
Ingresá la hora de inicio como número entero entre 8 y 23 (ejemplo: 20).\n
\n
Escribí *0* para hablar con un asesor.
```

---

## RAMA EVENTOS — MSG_EVT_05
**ID:** MSG_EVT_05
**Paso:** numero_ninos
**Objetivo:** Cantidad exacta de niños asistentes
**Aplica a:** subtipo NINOS

```
¿Cuántos niños van a participar?\n
\n
Ingresá un número entre 1 y 50.\n
Si son más de 50, escribí *0* para hablar con un asesor.
```

---

## RAMA EVENTOS — MSG_EVT_MENU
**ID:** MSG_EVT_MENU
**Paso:** menu_preferido
**Objetivo:** Selección de menú para los niños
**Aplica a:** subtipo NINOS

```
¿Qué menú preferís para los chicos?\n
\n
*1.* 2 porciones de pizza\n
*2.* Pancho\n
*3.* Hamburguesa\n
\n
*0.* Hablar con un asesor
```

---

## RAMA EVENTOS — MSG_EVT_COSTO_MENU
**ID:** MSG_EVT_COSTO_MENU
**Tipo:** INFORMATIVO (no requiere respuesta — el bot envía y continúa automáticamente)
**Aplica a:** subtipo NINOS
**Nota:** {{numero_ninos}}, {{pack_label}}, {{costo_menu_calculado}} son variables calculadas en runtime

```
Para {{numero_ninos}} niños con {{pack_label}}, el costo estimado del menú es de ${{costo_menu_calculado}}. 🧮\n
\n
A continuación te hacemos algunas preguntas más para completar tu presupuesto.
```

---

## RAMA EVENTOS — MSG_EVT_ADULTOS
**ID:** MSG_EVT_ADULTOS
**Paso:** numero_adultos
**Objetivo:** Cantidad de adultos asistentes e información sobre su menú
**Aplica a:** subtipo NINOS

```
¿Cuántos adultos van a asistir al evento?\n
\n
Ingresá un número (puede ser 0).\n
\n
ℹ️ Los adultos pueden optar por:\n
• Menú fijo: ${{precio_menu_adulto}} por persona (se suma al presupuesto)\n
• Cafetería libre: disponible en el lugar, no incluida en el pack
```

---

## RAMA EVENTOS — MSG_EVT_MENU_ADULTOS
**ID:** MSG_EVT_MENU_ADULTOS
**Paso:** menu_adultos
**Objetivo:** Determinar cuántos adultos toman menú fijo
**Aplica a:** subtipo NINOS
**Nota:** Solo se envía si numero_adultos > 0. {{numero_adultos}} = variable

```
De los {{numero_adultos}} adultos, ¿cuántos van a tomar el menú fijo?\n
\n
Ingresá un número entre 0 y {{numero_adultos}}.
```

---

## RAMA EVENTOS — MSG_EVT_ADICIONALES
**ID:** MSG_EVT_ADICIONALES
**Paso:** alimentos_adicionales
**Objetivo:** Selección de alimentos adicionales
**Aplica a:** subtipo NINOS

```
¿Querés agregar alimentos adicionales al evento? (no incluidos en el pack base)\n
\n
*1.* 🍟 Bandejas de Papas Fritas Calientes\n
*2.* 🥪 Sándwiches de Miga\n
*3.* 🍉 Bandejas de Frutas\n
*4.* 🍦 Helados (Palito de Agua o Bombón Helado)\n
\n
Podés elegir varias opciones separando los números con coma (ejemplo: *1,3*).\n
Si no querés adicionales, respondé *ninguno*.\n
\n
*0.* Hablar con un asesor
```

---

## RAMA EVENTOS — MSG_EVT_FERIADO
**ID:** MSG_EVT_FERIADO
**Estado:** ⚠️ ELIMINADO DEL FLUJO — ya no se envía al cliente
**Nota:** El feriado se detecta automáticamente al ingresar la fecha (tabla de feriados nacionales argentinos). El recargo del 30% se aplica si corresponde sin preguntar al usuario.

## RAMA EVENTOS — MSG_EVT_ADICIONAL_QTY
**ID:** MSG_EVT_ADICIONAL_QTY
**Paso:** cantidad por ítem de alimentos_adicionales (sub-paso dinámico)
**Objetivo:** Preguntar cuántas unidades de cada adicional seleccionado
**Aplica a:** subtipo NINOS — se repite por cada ítem seleccionado (excepto "ninguno")
**Nota:** {{item_name}} = nombre del ítem (ej. "🍟 Bandejas de Papas Fritas")

```
¿Cuántas {{item_name}} querés agregar?\n
\n
Ingresá un número entero.
```

---

## RAMA EVENTOS — MSG_EVT_EXTRAS
**ID:** MSG_EVT_EXTRAS
**Paso:** extras_texto
**Objetivo:** Registrar pedidos especiales o extras a confirmar por el asesor
**Aplica a:** subtipo NINOS

```
¿Hay algo especial que quieras agregar o consultar para el evento?\n
\n
Podés escribirlo libremente. Un asesor lo va a revisar y confirmar.\n
Si no tenés extras, respondé *ninguno*.
```

---

## RAMA EVENTOS — MSG_EVT_07
**ID:** MSG_EVT_07
**Paso:** nombre_responsable
**Objetivo:** Nombre del responsable del evento

```
¿A nombre de quién registramos el evento?\n
\n
*1.* Mi nombre (uso el nombre con el que estoy registrado)\n
*2.* Ingresar otro nombre\n
\n
*0.* Hablar con un asesor
```

---

## MSG_CONFIRMACION
**ID:** MSG_CONFIRMACION
**Estado:** CONFIRMACION
**Nota:** {{resumen}} es el bloque generado dinámicamente con los datos recolectados

```
Perfecto, revisá el resumen de tu reserva:\n
\n
{{resumen}}\n
\n
¿Confirmamos?\n
\n
*SI* — Confirmar reserva\n
\n
*0.* Hablar con un asesor (para cancelar u otras consultas)
```

---

## MSG_RESERVA_EXITOSA
**ID:** MSG_RESERVA_EXITOSA
**Estado:** COMPLETADO

```
✅ ¡Tu reserva está confirmada!\n
\n
Guardamos todos los datos. Si necesitás hacer algún cambio o tenés alguna consulta, no dudes en escribirnos.\n
\n
📍 *Cómo llegar:*\n
• Estacionamiento gratuito: Yerbal 1201\n
• Entrada peatonal: Yerbal 1255\n
\n
¡Hasta pronto en El Anden! 🌿
```

---

## MSG_RESERVA_PRECONFIRMADA
**ID:** MSG_RESERVA_PRECONFIRMADA
**Estado:** COMPLETADO (reserva futura pendiente de confirmación de asesor)

```
✅ ¡Tu pre-reserva fue registrada!\n
\n
Un asesor de El Anden se va a comunicar con vos para confirmarla.\n
\n
📍 *Cómo llegar:*\n
• Estacionamiento gratuito: Yerbal 1201\n
• Entrada peatonal: Yerbal 1255\n
\n
¡Hasta pronto! 🌿
```

