# Reserva de restaurante por WhatsApp Flow — recomendaciones de UX (pendientes de definir)

Nada de esto está implementado. Son propuestas para decidir después de que el Flow base esté andando.
Cada punto indica el impacto en mensajes salientes (lo que se cobra) y el esfuerzo estimado.

## 1. Menos fricción dentro del Flow (0 mensajes extra)

| # | Recomendación | Por qué | Esfuerzo |
|---|---|---|---|
| 1.1 | **Calendario con días sin cupo deshabilitados** (`CalendarPicker` + `unavailable-dates`) en lugar de `DatePicker` | Hoy el cliente elige una fecha llena y recién ahí ve el error. Deshabilitar los días completos de antemano evita la vuelta atrás. | Bajo: el endpoint ya calcula disponibilidad; hay que hacerlo para 8 días en el INIT. |
| 1.2 | **Turno y sector en una sola lista** ("20 hs · Galería", "20 hs · Patio"…) | Ahorra un toque y el `data_exchange` intermedio al elegir turno. Contra: lista más larga (máx. ~30 ítems). | Bajo |
| 1.3 | **Indicar escasez**: "Quedan pocos lugares" cuando un turno supera el umbral de alerta (70 % del cupo ya usado) | Incentiva cerrar la reserva y reduce abandonos. | Bajo |
| 1.4 | **Pantalla de resumen antes de confirmar** | Menos errores (fecha equivocada) y menos cancelaciones posteriores, que hoy terminan en conversación con asesor. Contra: una pantalla más. | Bajo |
| 1.5 | **Precargar lo último que eligió** el cliente (personas, sector) con `init-value` | Clientes frecuentes reservan en 2 toques. | Bajo |
| 1.6 | **Checkboxes de necesidades comunes** (cumpleaños, silla de bebé, celíacos, accesibilidad) además del texto libre | Datos estructurados para cocina/salón; el texto libre queda para lo excepcional. | Bajo |
| 1.7 | **Grupos de 15+: explicar el menú fijo de fin de semana dentro del Flow** antes de enviar la consulta | Hoy ese aviso va por chat; en el Flow no cuesta mensajes. | Bajo |

## 2. Mensaje de confirmación (sigue siendo 1 solo mensaje)

| # | Recomendación | Por qué |
|---|---|---|
| 2.1 | Incluir **cómo cancelar** ("Si no podés venir, respondé *D* para cancelar") | Libera cupo en vez de un no-show. |
| 2.2 | Link a **"Agregar a mi calendario"** (archivo .ics o link de Google Calendar) | Recordatorio sin costo para el club (no requiere template de recordatorio). |
| 2.3 | Link de **cómo llegar** (Google Maps) en lugar de solo la dirección | Ya existe el texto de estacionamiento; sumarle el link. |

## 3. Cosas que agregan mensajes (evaluar costo vs. beneficio)

| # | Recomendación | Costo |
|---|---|---|
| 3.1 | **Recordatorio el día de la reserva** | Fuera de la ventana de 24 hs requiere template de utilidad (se cobra). Medir tasa de no-show antes de decidir. |
| 3.2 | **Lista de espera** ("avisame si se libera un lugar") | 1 mensaje (probablemente template) cuando se libera cupo. |
| 3.3 | **Flow de "Mis reservas"** para cancelar/modificar reutilizando el mismo endpoint | 1 mensaje (el del Flow) + 1 confirmación; reemplaza el ida y vuelta actual de cancelación (3+ mensajes). |

## 4. Robustez y medición

| # | Recomendación |
|---|---|
| 4.1 | **Fallback si el endpoint está caído**: si el cliente escribe "reservar" y el Flow falló, ofrecer el flujo por texto (el viejo) en vez de un error. |
| 4.2 | **Medir abandono por pantalla** con la tabla `whatsapp_flow_tokens` (enviado → abierto → pantalla 2 → completado). |
| 4.3 | **Alertar al panel** cuando un día/turno se llena, igual que la alerta de ocupación por sector que ya existe. |

## 5. Reglas de negocio a confirmar (surgieron al implementar)

1. **Fin de semana 12:00**: `MSG_RES_FIN_DE_SEMANA` dice que el turno del mediodía es de 12 a 16 hs, pero los turnos de fin de semana (`MSG_RES_FINDE_TURNOS`) no tienen hora de fin. Hoy el Flow trata 12:00 y 14:00 de fin de semana como turnos **independientes** (cada uno con su 70 %). Si el de 12 debe descontar del de 14 (como en la semana), hay que agregar "(hasta las 16 hs)" al texto del turno o definir la duración.
2. **Turnos de noche (20 y 22 hs)**: no tienen hora de fin, así que no se superponen. Si una mesa de las 20 sigue ocupada a las 22, hay que definir la duración.
3. **Reservas "Sin preferencia"**: no ocupan un sector puntual, solo cuentan contra la capacidad total. Si se llenan los sectores al 70 % y además hay muchas "sin preferencia", el salón puede quedar muy cargado. Definir si el asesor asigna sector a esas reservas.

## 6. Bugs del flujo viejo (por texto) encontrados en el análisis

- **Paso de personas**: el mensaje ofrece letras A–F pero el bot solo acepta números; responder "B" cuenta como inválido (`BotEngine::resolvePersonasRestaurante`).
- **Fecha "dd/mm"**: si el mes actual es anterior a noviembre, una fecha pasada se guarda sin avisar y recién se rechaza al confirmar (`BotEngine::parseRestauranteDate`).
