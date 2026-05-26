# Plan de Cambios — Club El Andén (v3 — FINAL PARA CONFIRMAR)

---

## 3. Alerta de alta ocupación → Notificación en panel admin

**Qué hace:**
Cuando un sector del restaurante alcanza el umbral configurado (default 70%), aparece una notificación en el panel web del admin.

**La notificación dice:**
> ⚠️ Alcanzamos el 70% de la capacidad en *Terraza*. ¿Querés que informemos a quienes reservan que no hay más cupo?

**Dos botones:**

| Botón | Tooltip (hover) | Qué hace |
|-------|----------------|----------|
| **Sí, informar** | "El sector se marcará como sin disponibilidad. Los clientes que intenten reservarlo verán que no hay cupo." | Marca el sector como "cerrado" en el bot. El sector desaparece de las opciones o muestra "sin capacidad" en WhatsApp. |
| **No, mantener mensaje** | "El sector seguirá apareciendo como disponible. Los clientes podrán seguir eligiéndolo." | No hace nada, descarta la notificación. |

**Implementación técnica:**
- Nueva tabla `panel_notifications` (id, tipo, payload JSON, leída, created_at)
- Nuevo campo `sector_alerta_pct` (int, default 70) en `restaurant_configs`
- `RestaurantCapacity` emite notificación cuando supera el umbral (solo una vez por sector/fecha, no repetir)
- Componente de notificaciones en el panel (bell icon o banner) con polling cada 30s o al cargar página
- Botón "Sí, informar": llama endpoint que marca `RestaurantConfig.sector_[x]_cerrado = true`, se refleja en bot automáticamente
- Botón "No, mantener": marca notificación como leída, no cambia nada

❓ **3a:** Las notificaciones del panel, ¿dónde las querés ver?
- a) Un ícono de campana (🔔) en el header del panel con un contador de no leídas
- b) Un banner/alert en la página del dashboard
- c) En la página `/bot/messages` directamente
- d) Otro

❓ **3b:** Cuando el admin hace click en "Sí, informar" y el sector queda marcado como cerrado, ¿cómo se "reabre"? ¿Automáticamente al día siguiente, o el admin lo reactiva manualmente desde alguna página?

---

## 4. Auto-confirmación de reservas + notificación en panel

**Lógica del job:**
- Corre todos los días entre las **7 y las 23 hs** (runs a las 7, 11, 15, 19, 23)
- Busca reservas de **restaurante** con estado `PENDIENTE_CONFIRMACION` y `fecha_reserva` dentro de las próximas 24 hs
- Las **auto-confirma** → cambia estado a `CONFIRMADA`
- Genera una **notificación en el panel** por cada lote confirmado:
  > ✅ Se confirmaron automáticamente X reservas de restaurante para las próximas 24 hs.

❓ **4a:** Cuando se auto-confirma una reserva, ¿le mandamos un WhatsApp al **cliente** avisándole que está confirmada, o solo lo registramos internamente?

---

## 8a. Mensajes no-texto (sticker, audio, imagen, video)

**Cambio:** Si llega un sticker, audio, imagen, video o documento, el bot responde con `MSG_OPCION_INVALIDA` y no avanza el estado de la sesión.

✅ **Sin preguntas — listo para implementar.**

---

## 8b. Anti-spam / debounce (5 segundos)

**Cambio:** Si el mismo número manda más de un mensaje en menos de **5 segundos**, solo se procesa el primero. Los siguientes se descartan silenciosamente.

**Implementación:** `Cache::add("debounce:{phone}", true, 5)` en el job — si ya existe la clave, se ignora el mensaje.

✅ **Sin preguntas — listo para implementar.**

---

## Resumen

| # | Tarea | Estado |
|---|-------|--------|
| 3 | Notificación en panel + botones + sector configurable | ❓ Falta respuesta 3a y 3b |
| 4 | Job auto-confirm 7-23hs + notificación panel | ❓ Falta respuesta 4a |
| 8a | Non-text → opción inválida | ✅ Listo |
| 8b | Debounce 5s | ✅ Listo |

**Orden de implementación una vez confirmado:**
1. 8b (debounce — 1 archivo, cambio mínimo)
2. 8a (non-text messages — 1 archivo)
3. 4 (job scheduler + notificaciones panel)
4. 3 (alerta ocupación + panel + botones)
