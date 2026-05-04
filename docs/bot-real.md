# Bot real (WhatsApp Cloud API + atención humana)

Plan para llevar el simulador actual a producción contra Meta WhatsApp Business y agregar la posibilidad de que un asesor humano tome el control de la conversación.

> **Estado:** propuesta para revisar. Nada implementado todavía.

---

## 1. Objetivo

1. **Bot real conectado a Meta**: misma lógica del simulador (`BotEngine`), pero el iniciador de la conversación es el usuario en WhatsApp y los mensajes salen por la Cloud API.
2. **Botón de pausa por asesor**: cuando el bot escala a humano (o el asesor decide intervenir), un asesor logueado en el panel puede pausar el bot para esa conversación y responder manualmente.
3. **Resumen automático para el asesor**: al abrir un chat, el asesor ve de un vistazo el estado de la sesión + los últimos mensajes, sin tener que leer toda la conversación.

---

## 2. Arquitectura

```
WhatsApp (usuario)
       │
       ▼
Meta Cloud API ── webhook POST ──►  WhatsAppWebhookController
                                         │
                                         ├─► registra mensaje entrante en conversation_messages
                                         │
                                         ├─► si BotSession.estado_actual = PAUSADO_ASESOR  →  STOP (no responde el bot)
                                         │
                                         └─► BotEngine::process()  →  array<string> respuestas
                                                  │
                                                  └─► WhatsAppSender::send()  →  Meta Cloud API  →  WhatsApp (usuario)
                                                            │
                                                            └─► registra mensaje saliente en conversation_messages

Asesor (panel React)
       │
       ▼
GET /inbox             → lista de conversaciones (BotSession + último mensaje + estado)
GET /inbox/{numero}    → vista de chat con resumen + historial
POST /inbox/{numero}/pause  → pausa el bot
POST /inbox/{numero}/resume → reanuda el bot
POST /inbox/{numero}/reply  → manda mensaje del asesor por la Cloud API
```

---

## 3. Componentes nuevos

### 3.1 Backend

| Archivo | Rol |
|---|---|
| `app/Services/Meta/WhatsAppClient.php` | Wrapper HTTP de la Cloud API (`Http::withToken`). Métodos: `sendText($to, $body)` y `verifyWebhookSignature($payload, $sig)`. `sendTemplate` queda fuera de scope hasta que necesitemos mensajes proactivos (ver §8). |
| `app/Services/Meta/WhatsAppSender.php` | Toma el array de respuestas que devuelve `BotEngine::process()` y las manda una por una con `WhatsAppClient`. Persiste cada salida en `conversation_messages`. |
| `app/Http/Controllers/WhatsAppWebhookController.php` | `GET` para handshake (`hub.challenge`) + `POST` para recibir mensajes. Valida firma `X-Hub-Signature-256`, despacha al `BotEngine` (a menos que esté pausado), encola la respuesta. |
| `app/Http/Controllers/InboxController.php` | Lista, detalle, pause/resume, reply manual. |
| `app/Jobs/ProcessIncomingWhatsAppMessage.php` | Procesa cada mensaje entrante en cola (queue=database) — Meta exige responder al webhook en <5s, así que el procesamiento real va asíncrono. |
| `app/Models/ConversationMessage.php` | Nuevo modelo para loguear todo. |

### 3.2 Frontend

| Archivo | Rol |
|---|---|
| `resources/js/pages/inbox.tsx` | Lista de conversaciones (lado izq.) + chat seleccionado (centro) + panel de sesión (der., con el botón pausar). |
| `resources/js/components/inbox/conversation-list.tsx` | Items con número, nombre cliente, último mensaje, badge de estado. |
| `resources/js/components/inbox/conversation-chat.tsx` | Chat (visualmente igual al `bot-simulator`) con historial completo + composer del asesor. |
| `resources/js/components/inbox/session-summary.tsx` | Reusa el `SessionPanel` actual + agrega el botón "Pausar bot" / "Reanudar bot" + el resumen del paso actual. |

---

## 4. Cambios al `BotEngine`

`process()` devuelve `array<string>`. Lo que cambia respecto al simulador original es **quién consume ese array**:

- Simulador → llama a `BotEngine::logOutbound()` y la UI lo dibuja.
- Webhook → `WhatsAppSender::sendBotResponses()` lo manda por la Cloud API y persiste con `wa_message_id`.

Estado actual del refactor (✅ implementado):

1. **`process()` loguea inbound automáticamente** y delega el outbound al caller. Esto evita doble-logueo cuando el sender quiere asociar el `wa_message_id` de Meta a la fila.
2. **`escalate()` persiste `motivo_pausa` y `estado_previo_pausa`**. Antes el parámetro `$motivo` se ignoraba (era un bug latente).
3. **`logOutbound(BotSession, body, ?waId, ?sender)`** es ahora un método público sobre `BotEngine`, usado por simulador (sin `waId`) y sender (con `waId`, `sender = bot|advisor`).

Pendiente (Fase 5):

- **Reanudación con confirmación / cap de 12h** (§6.1). Hoy el `BotEngine` reanuda solo a las 12h para todos los `motivo_pausa`. Hay que diferenciar `ASESOR_TAKEOVER` (cap a 12h con reset a `INICIO` + `MSG_TIMEOUT_ASESOR`) del resto (siguen reanudando como hoy).

---

## 5. Modelo de datos

### 5.1 Cambios a `bot_sessions` (✅ aplicado en migración `2026_05_02_191011_extend_bot_sessions_for_advisor`)

```sql
ALTER TABLE bot_sessions ADD COLUMN motivo_pausa VARCHAR(50) NULL;            -- SOLICITUD_CLIENTE | ASESOR_TAKEOVER | etc.
ALTER TABLE bot_sessions ADD COLUMN estado_previo_pausa VARCHAR(50) NULL;     -- estado antes de pausar, para retomar
ALTER TABLE bot_sessions ADD COLUMN next_resume_check_at TIMESTAMP NULL;      -- cuándo volver a preguntar al asesor (§6.1)
ALTER TABLE bot_sessions ADD COLUMN resolved_by_advisor_at TIMESTAMP NULL;    -- el asesor marcó "Solucionado"
ALTER TABLE bot_sessions ADD COLUMN last_message_at TIMESTAMP NULL;           -- para ordenar la inbox
ALTER TABLE bot_sessions ADD COLUMN unread_count INT DEFAULT 0;               -- mensajes nuevos que el asesor no vio
```

> Sistema mono-asesor (Administración Anden). No hace falta trackear *quién* pausó, sólo *que* está pausado.

### 5.2 Tabla nueva `conversation_messages` (✅ aplicado en `2026_05_02_191012_create_conversation_messages_table`)

Necesaria porque antes **no se persistía el historial**, solo el estado actual.

```sql
CREATE TABLE conversation_messages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    bot_session_id BIGINT NOT NULL,            -- FK bot_sessions
    direction ENUM('inbound', 'outbound') NOT NULL,
    sender ENUM('user', 'bot', 'advisor') NOT NULL,
    body TEXT NOT NULL,
    wa_message_id VARCHAR(255) NULL,           -- ID que devuelve Meta (para tracking de status)
    wa_status VARCHAR(20) NULL,                -- sent|delivered|read|failed
    created_at TIMESTAMP,
    INDEX (bot_session_id, created_at)
);
```

### 5.3 Diagrama de estados de `bot_sessions.estado_actual`

```
INICIO
  ↓
REGISTRO_CLIENTE → MENU_PRINCIPAL → RECOLECTANDO_DATOS → CONFIRMACION → COMPLETADO
                       ↓                  ↓                  ↓            ↓
                       └──────────────────┴──────────────────┴────────────┘
                                          ↓
                                       PAUSADO  (motivo_pausa = ?)
                                          │
                                          ├── SOLICITUD_CLIENTE        → bot reanuda solo a las 12h
                                          ├── OPCIONES_INVALIDAS_*     → bot reanuda solo a las 12h
                                          ├── CAPACIDAD_EXCEDIDA       → bot reanuda solo a las 12h
                                          └── ASESOR_TAKEOVER          → bot reanuda con confirmación (ver §6.1)
```

### 5.4 Campos para el flujo de confirmación (§6.1)

Los 3 campos adicionales (`resolved_by_advisor_at`, `next_resume_check_at`, `estado_previo_pausa`) están incluidos en la migración de §5.1 y se usan en Fase 5 cuando se construya el flujo `ASESOR_TAKEOVER`.

---

## 6. Flujo de pausa por asesor

1. Llega un mensaje "0" o "atencion" → `BotEngine` ya escala (`PAUSADO` + `motivo_pausa = SOLICITUD_CLIENTE`).
2. Frontend del inbox recibe la notificación (badge nuevo en la conversación).
3. Asesor abre la conversación → ve el resumen + historial.
4. (Opcional, para casos donde el bot no escaló pero el asesor quiere intervenir igual) El asesor toca **"Pausar bot"**:
   - `POST /inbox/{numero}/pause` → setea `estado_actual = PAUSADO`, `motivo_pausa = ASESOR_TAKEOVER`, guarda `estado_previo_pausa` con el estado anterior.
   - El bot deja de responder a ese número.
5. Asesor responde manualmente desde el composer → cada mensaje sale por la Cloud API y se guarda en `conversation_messages` con `sender = advisor`.
6. Cuando termina, el asesor tiene **dos formas** de cerrar la atención humana (ver §6.1).

### 6.1 Cierre de la atención humana

Hay dos caminos, no excluyentes:

**A) Botón "Solucionado" (manual, en cualquier momento)**
- Visible en el panel lateral del chat mientras `motivo_pausa = ASESOR_TAKEOVER`.
- Click → `POST /inbox/{numero}/resolve`:
  - Setea `resolved_by_advisor_at = now()`, `motivo_pausa = NULL`, `next_resume_check_at = NULL`.
  - Reanuda el bot al `estado_previo_pausa` (o `INICIO` si era reset).
- Este es el camino "feliz": el asesor cerró el tema y se acuerda de marcarlo.

**B) Caja de confirmación automática (a partir de 1h, por si se olvidó)**
- Un job programado (`scheduler` cada 10min) busca sesiones con `motivo_pausa = ASESOR_TAKEOVER` y `next_resume_check_at <= now()`.
- La primera revisión se agenda al pausar: `next_resume_check_at = paused_at + 1h`.
- Cuando dispara, en la próxima request del frontend la inbox muestra una **caja modal** sobre la conversación:

```
┌─ ¿Resolviste la conversación con Juan Pérez? ─┐
│                                                 │
│  Hace 1h pausaste el bot para hablar con       │
│  este cliente. ¿Ya solucionaste su consulta?   │
│                                                 │
│  [ Sí, reanudar bot ]  [ Todavía no ]           │
└─────────────────────────────────────────────────┘
```

- **"Sí, reanudar bot"** → mismo efecto que el botón Solucionado (camino A).
- **"Todavía no"** → setea `next_resume_check_at = now() + 1h`. La caja vuelve a aparecer cada 1h hasta que confirme **o** hasta el cap de 12h (ver C).

**C) Cap duro: reanudación automática a las 12h**
- Si pasaron **12h desde `paused_at`** y el asesor nunca tocó "Solucionado" ni "Sí, reanudar bot" en la caja:
  - El job (mismo `scheduler` cada 10min) fuerza la reanudación: `estado_actual = INICIO`, `motivo_pausa = NULL`, `estado_previo_pausa = NULL`, `datos_parciales = {}`, `contador_invalidos = 0`.
  - **Manda al usuario un mensaje de disculpas** vía WhatsApp explicando que la atención humana se demoró y que tiene que arrancar de nuevo. Texto editable en la tabla `bot_messages` con key nueva `MSG_TIMEOUT_ASESOR` (categoría `general`). Ejemplo: *"Disculpá la demora en responderte. Pasaron varias horas desde tu última consulta así que vamos a empezar de nuevo para asegurarnos de tener tus datos al día. ¿En qué te ayudamos?"*
- A diferencia de los caminos A y B, este reset es **al estado `INICIO`** — no al `estado_previo_pausa` — porque tras 12h de espera los datos parciales que tenía el cliente probablemente ya no apliquen (cambió la fecha que pidió, etc.).

> **Por qué este diseño:** la caja a la 1h ataca el caso normal (asesor olvidó marcar como resuelto), y el cap de 12h asegura que ningún chat quede pausado indefinidamente sin mala UX para el usuario final.

### 6.2 Reanudación: ¿estado previo o desde INICIO?

Depende del camino de cierre:

| Camino | Estado al reanudar | Mensaje al usuario |
|---|---|---|
| **A** Botón "Solucionado" | `estado_previo_pausa` | (ninguno) |
| **B** "Sí, reanudar bot" en la caja a la 1h | `estado_previo_pausa` | (ninguno) |
| **C** Cap automático a las 12h | `INICIO` (datos parciales se descartan) | `MSG_TIMEOUT_ASESOR` (disculpas + pedir empezar de nuevo) |

Adicionalmente, hay un botón **"Reiniciar conversación"** disponible en cualquier momento que fuerza `estado_actual = INICIO` y descarta `datos_parciales`.

---

## 7. Resumen para el asesor

Cuando el asesor abre un chat, ve un **panel lateral** (similar al `SessionPanel` del simulador) con:

```
┌─ Resumen ─────────────────────────────────┐
│ Cliente:    Juan Pérez                    │
│ Teléfono:   +54 9 11 0000-0001            │
│ Reservas:   3 deportes · 1 restaurante    │
│                                            │
│ Estado actual:     Recolectando datos     │
│ Rama:              Eventos                │
│ Subtipo:           Cumpleaños infantil    │
│ Paso pendiente:    Cantidad de niños      │
│                                            │
│ Datos recolectados:                        │
│   Fecha:        2026-05-20                │
│   Hora:         16:00                     │
│   Pack:         Pack Básico               │
│                                            │
│ Motivo de pausa:   Solicitud del cliente   │
│ Pausado hace:      3 minutos              │
└────────────────────────────────────────────┘
[ Pausar bot ] / [ Reanudar bot ] [ Reiniciar ]
```

Esto se arma 100% a partir de campos que ya existen en `bot_sessions` + `clientes`. **No hace falta IA / NLP** — todo es información estructurada que ya guardamos. El asesor sabe en ~3 segundos en qué punto del flujo estaba el cliente.

### 7.1 Mapeo de identificadores → etiquetas humanas

Los valores que guardamos en BD (`RECOLECTANDO_DATOS`, `cantidad_ninos`, etc.) se traducen a textos amigables antes de mostrarse. La traducción vive en un archivo simple, **no en BD**, para que sea trivial mantenerla:

`resources/js/lib/session-labels.ts`

```ts
export const ESTADO_LABELS: Record<string, string> = {
    INICIO:               'Inicio',
    REGISTRO_CLIENTE:     'Registrando cliente',
    MENU_PRINCIPAL:       'En menú principal',
    RECOLECTANDO_DATOS:   'Recolectando datos',
    CONFIRMACION:         'Esperando confirmación',
    COMPLETADO:           'Reserva completada',
    CAMBIANDO_DATO:       'Modificando un dato',
    PAUSADO:              'Pausado',
};

export const RAMA_LABELS: Record<string, string> = {
    DEPORTES:    'Deportes',
    RESTAURANTE: 'Restaurante',
    EVENTOS:     'Eventos',
};

export const SUBTIPO_LABELS: Record<string, string> = {
    CUMPLEAÑOS_INFANTIL: 'Cumpleaños infantil',
    CUMPLEAÑOS_ADULTO:   'Cumpleaños adulto',
    EVENTO_CORPORATIVO:  'Evento corporativo',
    // ... según los subtipos reales del flujo
};

export const STEP_LABELS: Record<string, string> = {
    fecha:           'Fecha',
    hora:            'Hora',
    cantidad_ninos:  'Cantidad de niños',
    cantidad_adultos:'Cantidad de adultos',
    pack_label:      'Pack',
    // ... uno por step del flujo
};

export const MOTIVO_PAUSA_LABELS: Record<string, string> = {
    SOLICITUD_CLIENTE:           'Solicitud del cliente',
    OPCIONES_INVALIDAS_REITERADAS: 'Opciones inválidas reiteradas',
    CAPACIDAD_EXCEDIDA:          'Capacidad excedida',
    ASESOR_TAKEOVER:             'Tomado por asesor',
};

// Fallback por si aparece un valor no mapeado:
export const labelize = (raw: string | null, map: Record<string, string>) =>
    raw ? (map[raw] ?? raw.replaceAll('_', ' ').toLowerCase()) : '—';
```

> **Por qué un archivo TS y no la BD:**
> - Los identificadores (`RECOLECTANDO_DATOS`, etc.) son enums del código, no datos editables — su etiqueta humana también es parte del código.
> - Cualquier estado/step nuevo que se agregue al `BotEngine` viene acompañado de su entrada en este archivo en el mismo PR, así no hay drift entre código y UI.
> - El `labelize()` con fallback evita romper la UI si en algún momento aparece un valor sin traducir (queda visible y obvio para arreglar).
>
> Los textos que **sí** son editables por negocio (mensajes que el bot manda al usuario) ya viven en `bot_messages` y se administran desde `/bot/messages`. Esto es distinto: son labels técnicos de admin interno.

---

## 8. Configuración de Meta

Variables nuevas en `.env`:

```
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_ACCESS_TOKEN=                   # token permanente del System User
WHATSAPP_WEBHOOK_VERIFY_TOKEN=           # string arbitrario que ponemos en Meta y verificamos en el GET
WHATSAPP_APP_SECRET=                     # para validar X-Hub-Signature-256 del POST
WHATSAPP_API_VERSION=v21.0
```

### Endpoints públicos a configurar en el panel de Meta:

- `GET /webhooks/whatsapp` → handshake (responde `hub.challenge` si `hub.verify_token` matchea).
- `POST /webhooks/whatsapp` → recibe mensajes (validar firma, encolar job, responder 200 inmediato).

### Plantillas de WhatsApp (Message Templates)

Meta exige que los **mensajes iniciados por el negocio** fuera de la ventana de 24h sean templates aprobados. Como nuestro bot **siempre responde a un mensaje del usuario**, estamos dentro de la ventana de 24h y podemos mandar texto libre. Solo necesitaríamos templates si en el futuro queremos mandar recordatorios proactivos (ej. "tu reserva es mañana"). Lo dejo fuera de scope por ahora.

---

## 9. Tiempo real en el inbox

Importante distinguir dos capas:

- **Backend ← Meta**: ya es push. Meta hace `POST` al webhook en cuanto el usuario manda el mensaje, lo tenemos en el server en milisegundos sin polling de nuestra parte.
- **Frontend ← Backend**: el browser del asesor no se entera solo. Para refrescar la vista sin recargar hay tres opciones:

| Opción | Latencia | Complejidad | Cuándo conviene |
|---|---|---|---|
| **A) Polling** cada 2-5s | 2-5s | Muy baja (10 líneas) | Equipos chicos (1-3 asesores), volumen bajo/medio |
| **B) SSE (Server-Sent Events)** | ~100ms | Baja (HTTP normal, sin infra extra) | Punto medio si polling se queda corto |
| **C) Laravel Reverb + Echo** (websockets) | ~100ms | Alta (servicio Reverb + Pusher protocol + reconexiones) | Volumen alto o muchos asesores concurrentes |

> 💡 **Tip / recomendación**
>
> Arrancar con **Opción A (polling)**: polling de 3s para la lista de conversaciones (`/inbox/poll` que devuelve `{ id, last_message_at, unread_count }[]`) y polling de 2s en el chat abierto (`/inbox/{numero}/poll?after={lastId}`).
>
> **Por qué:**
> - Para 1-3 asesores con docenas de chats simultáneos es indistinguible del tiempo real.
> - No agrega infraestructura nueva (sin Reverb, sin Pusher, sin worker websocket).
> - Si más adelante el volumen lo justifica, migramos a Reverb **sin tocar el resto del código** — solo cambia cómo el frontend recibe el evento.
>
> Hacer polling más agresivo que 2s (ej. 500ms) **no compensa**: gasta CPU y conexiones contra una mejora de latencia que el ojo humano no percibe en chat. Si necesitás <1s, ahí sí saltar a Reverb.

### 9.1 Notificaciones al asesor

Cuando el polling detecta una conversación nueva o escalada, la UI dispara:

1. **Badge** en el item "Inbox" del sidebar con la cantidad de chats pausados sin atender (ej. "Inbox · 3").
2. **Toast** ("nueva conversación de Juan Pérez") usando el `Toaster` de sonner que ya está montado en el app.
3. **Título de la pestaña parpadea** con el contador (ej. `(3) Club Anden`) — útil cuando el asesor tiene la pestaña en background.

Sonido / push / email quedan fuera de la versión inicial. Si después se quiere habilitar sonido es trivial (un `<audio>` que se dispara cuando aumenta el badge).

---

## 10. Seguridad

- **Validar firma `X-Hub-Signature-256`** en el `POST` del webhook (HMAC-SHA256 con `WHATSAPP_APP_SECRET`). Sin esto cualquiera puede mandar mensajes falsos.
- **Idempotencia**: Meta puede retransmitir webhooks. Guardar `wa_message_id` en `conversation_messages` con índice único, ignorar duplicados.
- **Rate limit** en `/inbox/{numero}/reply` (ej. throttle 30/min) para no quemar tokens de Meta si alguien spamea.
- El webhook `POST /webhooks/whatsapp` es público (Meta lo necesita), pero todo lo demás (`/inbox/*`) va detrás de `auth + verified` como las rutas existentes.

---

## 11. Fases de implementación

| Fase | Entregable | Estado |
|---|---|---|
| **0** | Esta especificación + decisiones tomadas | ✅ |
| **1** | Migraciones (`conversation_messages` + extensión `bot_sessions`) + modelo + logging desde el simulador (sin tocar Meta) | ✅ |
| **2** | `WhatsAppClient` + `WhatsAppSender` + webhook controller con job + idempotencia + verificación de firma | ✅ |
| **3** | Compartir `BotEngine` entre simulador y webhook (caller decide cómo loguear outbound) + simulador hidrata historial desde DB | ✅ |
| **4** | UI del Inbox (lista + chat + resumen + composer del asesor) + pausa/reanudar básico | ✅ |
| **5** | Flujo §6.1 completo: caja de confirmación a la 1h + cap automático a las 12h + `MSG_TIMEOUT_ASESOR` (job programado) | ✅ |
| **6** | Polling de inbox (3s) + chat (2s) + badge unread + toast + título parpadeante (§9.1) | ✅ |
| **7** | Hardening: rate limit en `/inbox/*/reply`, manejo de errores Meta (token expirado, número bloqueado, etc.), normalización de números | ✅ |
| **8** | Pruebas end-to-end con un número real de Meta + go-live | ⏳ |

### 11.1 Lo que está construido (fases 1-7)

- Migraciones aplicadas: `conversation_messages` (con `wa_message_id` único para idempotencia) y extensión de `bot_sessions` (`motivo_pausa`, `estado_previo_pausa`, `next_resume_check_at`, `resolved_by_advisor_at`, `last_message_at`, `unread_count`).
- Modelo `ConversationMessage` con constantes `DIRECTION_*` / `SENDER_*` y relación `BotSession::messages()`.
- `BotEngine::process()` → loguea inbound, devuelve respuestas; el outbound lo persiste el caller (simulador local vs `WhatsAppSender` con `wa_message_id`).
- Bug fix: `escalate()` ahora sí persiste `motivo_pausa` y `estado_previo_pausa` (antes el parámetro `$motivo` se ignoraba).
- `app/Services/Meta/WhatsAppClient.php`: `sendText()` y `verifyWebhookSignature()` (HMAC-SHA256, `hash_equals`).
- `app/Services/Meta/WhatsAppSender.php`: itera respuestas con best-effort (si una falla, marca `wa_status = failed` y sigue).
- `WhatsAppWebhookController`: `verify` (handshake) y `receive` (valida firma → encola `ProcessIncomingWhatsAppMessage`).
- Job idempotente por `wa_message_id` (chequeo de existencia antes de procesar).
- Wiring: `config/services.php`, `AppServiceProvider` (singleton del client), `bootstrap/app.php` (registra `routes/api.php`), `.env.example` (6 variables nuevas).
- Simulador: `BotSimulatorController::index()` carga el historial desde DB → la página React hidrata el estado al montarse, manteniendo la conversación entre recargas. `reset` borra mensajes y sesión en cascada.

**Fase 4 — Inbox (✅):**
- `InboxController` con endpoints: `index` (lista), `show/{numero}` (detalle con historial), `pause`, `resume`, `restart`, `reply`, `markRead`, `poll` (esqueleto para Fase 6).
- Pausa básica: `POST /inbox/{numero}/pause` setea `motivo_pausa = ASESOR_TAKEOVER`, guarda `estado_previo_pausa`, agenda `next_resume_check_at = now + 1h`. `resume` revierte al estado previo.
- `markRead` resetea `unread_count` al 0; el `show()` también lo hace al abrir.
- `inboxUnreadTotal` shareado vía `HandleInertiaRequests` → todas las páginas tienen el contador disponible.
- UI: `pages/inbox.tsx` con layout split (lista 320px + chat flex + resumen 288px en desktop), modo single-pane en mobile con back button. Componentes: `conversation-list.tsx`, `conversation-chat.tsx` (estilo WhatsApp con bubbles diferenciados user/bot/advisor), `session-summary.tsx`.
- `lib/session-labels.ts`: traducción de identificadores enum (`RECOLECTANDO_DATOS` → "Recolectando datos") con fallback automático snake_case → "snake case" para evitar drift.
- Sidebar: nuevo item "Inbox" con `Inbox` lucide-icon + badge rojo (count de unread) que aparece automáticamente cuando hay conversaciones pausadas con mensajes nuevos.
- Composer del asesor: solo se habilita cuando la sesión está PAUSADA. Manda mensaje vía `POST /inbox/{numero}/reply` → `WhatsAppSender::sendAdvisorMessage` → Cloud API + persiste con `sender = advisor`.

**Lo que falta para que el composer mande de verdad:** cargar las 6 vars `WHATSAPP_*` en `.env`. Hoy si las dejás vacías el `WhatsAppClient` lanza al pegar contra Meta, el sender lo captura y marca `wa_status = failed`, pero la fila se persiste igual (UX "se mandó pero falló" se ve en el chat con un ⚠).

**Fase 5 — flujo de cierre §6.1 completo (✅):**
- Mensaje nuevo en `bot_messages`: `MSG_TIMEOUT_ASESOR` (categoría general). Migración idempotente (`2026_05_02_203217_seed_msg_timeout_asesor`) + entrada en `BotMessagesSeeder` + fallback hardcodeado en `BotMessages.php`.
- Endpoint `POST /inbox/{numero}/snooze` ("Todavía no" en la modal): solo posterga `next_resume_check_at = now + 1h`, no toca el resto del estado.
- Comando `inbox:sweep-takeovers` (Console command):
  - Busca sesiones con `motivo_pausa = ASESOR_TAKEOVER` y `timestamp_pausa <= now - 12h`.
  - Las resetea a `INICIO` (limpia `motivo_pausa`, `estado_previo_pausa`, `next_resume_check_at`, `timestamp_pausa`, `datos_parciales`, `rama_activa`, `subtipo_activo`, `current_step`, `unread_count`, `contador_invalidos`).
  - Le manda al usuario `MSG_TIMEOUT_ASESOR` vía `WhatsAppSender` (queda persistido en `conversation_messages` como outbound del bot).
  - Acepta `--cap-hours=N` para overridear el cap (útil en tests).
  - **Importante:** ignora sesiones con otros `motivo_pausa` (SOLICITUD_CLIENTE, etc.) — esas siguen el flujo viejo del `BotEngine` (auto-resume al recibir un mensaje del usuario).
- Schedule registrado en `routes/console.php` cada 10 min con `withoutOverlapping()`. Logs en `storage/logs/inbox-sweep.log`.
- Modal frontend: `resume-prompt-modal.tsx` aparece cuando `motivo_pausa = ASESOR_TAKEOVER` y `next_resume_check_at <= now`. Dos botones: "Sí, reanudar bot" (POST `/inbox/{numero}/resume`) y "Todavía no" (POST `/inbox/{numero}/snooze`). Estado local `resumePromptDismissed` evita re-show inmediato tras snooze.

**Fase 6 — polling + notificaciones (✅):**
- 2 endpoints livianos:
  - `GET /inbox/poll` → lista de conversaciones + `inboxUnreadTotal` + `now`. Mismo formato que `index()` pero sin renderizar Inertia.
  - `GET /inbox/{numero}/poll?after={lastId}` → mensajes con id > lastId (incremental) + estado actualizado de la sesión. Si la sesión no existe (ej. fue reseteada por el sweeper), devuelve `{ exists: false }`.
- 4 hooks React nuevos:
  - `useInboxPolling(initial)` — pollea `/inbox/poll` cada 3s. Retorna `{ conversations, unreadTotal }`. Detecta nuevas conversaciones escaladas (motivo_pausa pasó de null/!takeover a !null + unread > 0) y dispara toast vía `sonner`. Pause cuando la pestaña no es visible (`usePageVisibility`). Re-fetch inmediato al recuperar foco.
  - `useChatPolling(numero, initialMessages, initialSession)` — pollea `/inbox/{numero}/poll?after={lastId}` cada 2s. Mantiene un set de IDs ya vistos para dedup contra mensajes optimistas. Devuelve mensajes acumulados + sesión actualizada.
  - `usePageVisibility()` — `useSyncExternalStore` sobre Page Visibility API + `focus`/`blur` events. Devuelve boolean.
  - `useTitleFlash(count)` — actualiza `document.title` con `(N) ...` cuando la pestaña está oculta y count > 0; restaura al volver foco. Limpia el prefijo en cleanup.
- `inbox.tsx` integra los 3 hooks: `liveSelected.session` se usa en lugar del prop original para que `SessionSummary`, `ResumePromptModal` y `canReply` reaccionen a actualizaciones del server (ej. asesor abre la modal a la 1h sin recargar la página).
- Toast importa `toast` de `sonner` (Toaster ya estaba montado globalmente en `app.tsx`).
- Performance: polling pausado mientras tab oculto evita pegar al server inútilmente. Poll inmediato al recuperar foco compensa la latencia de "venir desde otro tab".

**Fase 7 — hardening (✅):**
- **Rate limit**: `/inbox/{numero}/reply` con `throttle:30,1` (middleware Laravel). Si el asesor (o un script malicioso) intenta superar 30 mensajes/min, devuelve 429.
- **Normalización de números**: helper `App\Support\PhoneNumber::normalize()` strip todo lo que no es dígito (`+54 9 11 0000-0099` → `5491100000099`). Aplicado en:
  - `WhatsAppWebhookController::extractMessages()` — antes de dispatchar el job (entry point).
  - `WhatsAppSender::safeSend()` — antes de pasar al client (defense in depth, asegura que Meta solo recibe dígitos).
- **Categorización de errores Meta**: nueva clase `App\Services\Meta\MetaApiException` con campo `kind` (auth/blocked/rate_limit/server/unknown). El client clasifica por HTTP status + Meta error code (190 → auth, 130429 → rate_limit, 131026 → blocked, etc.). El sender mapea cada kind a un `wa_status` específico (`failed_token`, `failed_blocked`, `failed_rate_limit`, `failed_server`, `failed`). Visible en la UI con el ⚠ del chat.
- **Logging estructurado**: cada error de Meta loguea `{ method, path, status, meta_code, kind, body }` para auditar fallos en producción.
- **Estado de éxito explícito**: el sender ahora setea `wa_status = 'sent'` cuando Meta acepta (antes quedaba null hasta que llegaran los webhooks de delivery status, que están fuera de scope).

---

## 12. Riesgos / cosas a vigilar

- **Token de Meta**: usar System User token (no expira) en vez del temporal de 24h.
- **Throughput de Meta**: la Cloud API tiene rate limits por número (250-1000 msgs/seg según tier). Para nuestro volumen no debería ser problema.
- **Mensajes con media**: hoy el bot solo manda texto plano. Si en el futuro queremos mandar imágenes (menú del restaurante, etc.) hay que extender `WhatsAppClient` y `bot_messages`.
- **Localización del cliente**: el `numero_contacto` viene como `5491100000001` (sin +). Asegurarse de normalizar al guardar/comparar.
- **Conversaciones huérfanas**: cubierto por el flujo de §6.1 (caja de confirmación a la 1h con reintento cada 1h + cap automático a las 12h). Igual conviene tener un badge de "pausados hace mucho" en la inbox para que sean visibles a primera vista.

---

## 13. Decisiones tomadas

> Todas las decisiones del diseño están confirmadas:
> - Asesor único: **Administración Anden** — sin asignación / multi-usuario.
> - Tiempo real: polling 3s/2s (§9).
> - `ASESOR_TAKEOVER`: caja de confirmación a la **1h** (reintento cada 1h) + cap automático a las **12h** que reinicia desde `INICIO` con mensaje de disculpas (§6.1).
> - Al reanudar por A/B: vuelve al `estado_previo_pausa`. Al reanudar por C (timeout 12h): `INICIO` + descarta datos parciales (§6.2).
> - Resumen del asesor: estructurado únicamente, sin LLM (§7).
> - Notificaciones: badge + toast + título de pestaña parpadeando, sin sonido (§9.1).

Fases **1-7 ✅ completadas**. Próximo paso: **Fase 8** (pruebas end-to-end con un número real de Meta + go-live).
