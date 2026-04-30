# LOGIC.MD — BOT EL ANDEN
# SOURCE OF TRUTH — NO MODIFICAR SIN AUTORIZACIÓN

---

## 1. ESTADOS DEL BOT

| Estado              | Descripción                                                         |
|---------------------|---------------------------------------------------------------------|
| INICIO              | Primera interacción del cliente — dispara lookup en BD              |
| REGISTRO_CLIENTE    | Cliente nuevo: esperando que ingrese su nombre                      |
| MENU_PRINCIPAL      | Esperando selección de rama de servicio                             |
| RECOLECTANDO_DATOS  | Completando atributos de la rama seleccionada, paso a paso          |
| CONFIRMACION        | Mostrando resumen y esperando confirmación final del cliente         |
| COMPLETADO          | Reserva guardada. Bot en espera de nueva interacción                |
| ESCALADO_HUMANO     | Bot pausado. Humano debe intervenir. Alerta emitida                 |
| PAUSADO             | Bot en pausa por 12 hs a partir del momento de escalado             |

---

## 2. TRANSICIONES DE ESTADO

### 2.1 INICIO — identificación del cliente
- Trigger: primera interacción (cualquier mensaje entrante, incluyendo `__init__`)
- Acción: buscar `numero_contacto` del cliente en `tabla_clientes`
  - **Cliente conocido** (registrado con nombre): enviar MSG_BIENVENIDA_CONOCIDO (incluye nombre), estado MENU_PRINCIPAL
  - **Cliente desconocido** (sin registro o sin nombre): enviar MSG_REGISTRO_PEDIR_NOMBRE, estado REGISTRO_CLIENTE

### 2.1b REGISTRO_CLIENTE
- Esperar respuesta del cliente (texto libre — el nombre)
- Guardar nombre como `nombre_cliente` en la sesión y en `tabla_clientes`
  - Si el registro no existe: crear nuevo cliente con `numero_contacto` + `nombre_cliente`
  - Si el registro existe sin nombre: actualizar `nombre_cliente`
- Enviar MSG_REGISTRO_BIENVENIDA (incluye nombre)
- Estado siguiente: MENU_PRINCIPAL

### 2.2 MENU_PRINCIPAL
- Esperar respuesta del cliente
- Respuestas válidas: `A`, `B`, `C`, `0`
- `A` → rama DEPORTES, enviar MSG_DEP_01, estado COMPLETADO (no recolecta datos, no requiere confirmación)
- `B` → rama RESTAURANTE, paso 1, estado RECOLECTANDO_DATOS
- `C` → rama EVENTOS, paso 1, estado RECOLECTANDO_DATOS
- `0` → estado ESCALADO_HUMANO
- Respuesta inválida → sumar 1 al contador de intentos inválidos del paso actual
  - Si contador_invalidos < 2: enviar MSG_OPCION_INVALIDA, permanecer en MENU_PRINCIPAL
  - Si contador_invalidos >= 2: estado ESCALADO_HUMANO

### 2.3 RECOLECTANDO_DATOS
- El bot sigue la secuencia de pasos definida en la tabla_maestra_reserva para la rama activa y el subtipo_activo
- Por cada paso:
  1. Enviar mensaje del paso (campo: id_mensaje)
  2. Esperar respuesta del cliente
  3. Validar respuesta contra respuestas_validas del paso
     - Válida: guardar en tabla_reservas (campo correspondiente), avanzar al paso siguiente, resetear contador_invalidos
     - Inválida: sumar 1 al contador_invalidos del paso
       - Si contador_invalidos < 2: enviar MSG_OPCION_INVALIDA, repetir mensaje del paso
       - Si contador_invalidos >= 2: estado ESCALADO_HUMANO
  4. Si la respuesta en cualquier paso es `0`: estado ESCALADO_HUMANO
- **Excepción — EVENTOS paso 1 (tipo_evento):**
  - Respuesta `1` (evento privado) → ESCALADO_HUMANO (motivo: SOLICITUD_CLIENTE)
  - Respuesta `2` → guardar tipo_evento, set subtipo_activo = 'NINOS', continuar con secuencia NINOS
  - Respuesta `3` → guardar tipo_evento, set subtipo_activo = 'GENERAL_EVT', continuar con secuencia GENERAL_EVT
  - Respuesta `4` → guardar tipo_evento, set subtipo_activo = 'GENERAL_EVT', continuar con secuencia GENERAL_EVT
- **Excepción — EVENTOS paso numero_ninos (subtipo NINOS):**
  - Respuesta debe ser entero entre 1 y 50 (ver sección 10.3)
  - Si valor > 50: ESCALADO_HUMANO (motivo: CAPACIDAD_EXCEDIDA)
- Al completar todos los pasos de la rama+subtipo: estado CONFIRMACION

### 2.4 CONFIRMACION
- Selección de mensaje según rama y condición de fecha:
  - RESTAURANTE, fecha ≤ 7 días desde hoy → MSG_RES_CONFIRMACION
  - RESTAURANTE, fecha > 7 días desde hoy → MSG_RES_CONFIRMACION_FUTURA
  - EVENTOS → MSG_CONFIRMACION
- Esperar respuesta del cliente
- Respuestas válidas: `SI`, `NO`, `CAMBIAR`
- `SI`:
  - RESTAURANTE, fecha ≤ 7 días → guardar con estado_reserva = 'CONFIRMADA', enviar MSG_RESERVA_EXITOSA, disparar recordatorio Google Calendar (si mail disponible), estado COMPLETADO
  - RESTAURANTE, fecha > 7 días → guardar con estado_reserva = 'PENDIENTE_CONFIRMACION', enviar MSG_RESERVA_EXITOSA, emitir alerta interna al asesor (motivo: RESERVA_FUTURA_PENDIENTE), estado COMPLETADO
  - EVENTOS → guardar con estado_reserva = 'CONFIRMADA', enviar MSG_RESERVA_EXITOSA, estado COMPLETADO
- `NO` → ESCALADO_HUMANO (motivo: SOLICITUD_CANCELACION). El asesor gestiona la cancelación.
- `CAMBIAR` → estado CAMBIANDO_DATO (ver sección 9, solo rama RESTAURANTE)
- Respuesta inválida → sumar 1 al contador_invalidos
  - Si contador_invalidos < 2: enviar MSG_OPCION_INVALIDA, repetir mensaje de confirmación
  - Si contador_invalidos >= 2: estado ESCALADO_HUMANO

### 2.5 COMPLETADO
- Bot en espera
- Si el cliente escribe nuevamente: reiniciar desde MENU_PRINCIPAL
- Si el cliente escribe `0` en cualquier momento: estado ESCALADO_HUMANO

### 2.6 ESCALADO_HUMANO
- Acciones a ejecutar (en orden):
  1. Enviar MSG_ESCALADO_HUMANO al cliente
  2. Emitir alerta interna (webhook / notificación) con:
     - numero_contacto del cliente
     - nombre_cliente (si ya fue recolectado)
     - rama_servicio activa (si aplica)
     - último paso completado
     - datos parciales recolectados hasta el momento
     - motivo del escalado: [SOLICITUD_CLIENTE | OPCIONES_INVALIDAS_REITERADAS]
  3. Registrar timestamp de inicio de pausa
  4. Estado: PAUSADO por 12 hs desde timestamp de escalado

### 2.7 PAUSADO
- El bot NO responde mensajes entrantes durante las 12 hs
- Al vencer las 12 hs: estado INICIO (reiniciar flujo)
- El humano puede liberar la pausa manualmente antes de las 12 hs

---

## 3. REGLA DE ESCALADO — DISPONIBLE EN TODO MOMENTO

- En cualquier estado, si el cliente envía `0` o escribe exactamente `atencion`:
  → interrumpir flujo actual → estado ESCALADO_HUMANO
- Esta regla tiene prioridad sobre cualquier otra validación

---

## 4. REGLA DE REINTENTOS INVÁLIDOS

- contador_invalidos se aplica POR PASO (se resetea al avanzar de paso)
- Umbral: 2 intentos inválidos en el mismo paso → ESCALADO_HUMANO
- Esta regla aplica en: MENU_PRINCIPAL, cada paso de RECOLECTANDO_DATOS, CONFIRMACION

---

## 5. SECUENCIA DE PASOS POR RAMA

La secuencia exacta de pasos se define en tabla_maestra_reserva.
El bot consulta esa tabla para saber:
- qué mensaje enviar en cada paso
- qué respuestas son válidas
- a qué campo de tabla_reservas mapea cada respuesta

### 5.1 Pasos rama DEPORTES
| Orden | nombre_atributo |
|-------|-----------------|
| 1     | link_reserva    |

> Rama informativa. El bot envía MSG_DEP_01 con el link externo y vuelve a estado COMPLETADO. No recolecta datos. No genera registro en tabla_reservas.

### 5.2 Pasos rama RESTAURANTE
| Orden | nombre_atributo    |
|-------|--------------------|
| 1     | fecha              |
| 2     | hora               |
| 3     | numero_personas    |
| 4     | sector             |
| 5     | nombre_responsable |
| 6     | mail_contacto      |

### 5.3 Pasos rama EVENTOS

**Paso universal (todos los subtipos):**
| Orden | nombre_atributo | Tipo de validación             |
|-------|-----------------|--------------------------------|
| 1     | tipo_evento     | Opciones predefinidas: 1/2/3/4 |

> Opción 1 → ESCALADO_HUMANO inmediato. Opciones 2/3/4 → continuar con sub-flujo correspondiente.

**Sub-flujo NINOS (tipo_evento = 2):**
| Orden | nombre_atributo      | Tipo de paso    | Tipo de validación                            |
|-------|----------------------|-----------------|-----------------------------------------------|
| 1     | pack_seleccionado    | RECOLECTAR      | Opciones predefinidas: 1/2/3/4                |
| 2     | fecha                | RECOLECTAR      | Libre — formato DD/MM/AA (ver sección 10.1)   |
| 3     | hora_inicio          | RECOLECTAR      | Libre — entero 8–23 (ver sección 10.2)        |
| 4     | numero_ninos         | RECOLECTAR      | Libre — entero 1–50 (ver sección 10.3)        |
| 4.5   | —                    | INFORMATIVO     | Enviar MSG_EVT_COSTO_MENU (sin esperar respuesta, ver sección 13) |
| 5     | menu_preferido       | RECOLECTAR      | Opciones predefinidas: 1/2/3                  |
| 6     | numero_adultos       | RECOLECTAR      | Libre — entero 0–999 (ver sección 13)         |
| 6.5   | —                    | CONDICIONAL     | Si numero_adultos > 0: enviar MSG_EVT_MENU_ADULTOS y recolectar menu_adultos; si = 0: saltar |
| 7     | menu_adultos         | RECOLECTAR COND.| Libre — entero 0 a numero_adultos             |
| 8     | alimentos_adicionales| RECOLECTAR      | Libre — lista 1,2,3,4 o 'ninguno'. Si hay ítems, preguntar cantidad de cada uno (ver sección 13) |
| 8.x   | —                    | SUB-PASOS       | Por cada ítem seleccionado: preguntar cantidad (entero ≥ 1) vía MSG_EVT_ADICIONAL_QTY |
| 9     | extras_texto         | RECOLECTAR      | Libre — texto o 'ninguno'                     |
| 10    | mail_contacto        | RECOLECTAR      | Email válido o 'no' para omitir               |
| 11    | nombre_responsable   | RECOLECTAR      | Opciones predefinidas: 1/2                    |

> **Nota:** El campo `es_feriado` ya NO se pregunta al cliente. Se detecta automáticamente al ingresar la fecha, consultando la tabla de feriados nacionales argentinos (sección 13.7).

**Sub-flujo GENERAL_EVT (tipo_evento = 3 o 4):**
| Orden | nombre_atributo    | Tipo de validación                            |
|-------|--------------------|-----------------------------------------------|
| 1     | fecha              | Libre — formato DD/MM/AA (ver sección 10.1)   |
| 2     | hora_inicio        | Libre — formato HH:MM 24 hs (ver sección 10.2)|
| 3     | numero_personas    | Libre — entero 1–999                          |
| 4     | nombre_responsable | Opciones predefinidas: 1/2                    |

---

## 6. IDENTIFICACIÓN DEL CLIENTE

- Al iniciar cualquier flujo, buscar el número de contacto entrante en tabla_clientes
  - Encontrado: cargar id_cliente, nombre_cliente, datos existentes
  - No encontrado: crear nuevo registro en tabla_clientes con el número de contacto
- El nombre_responsable recolectado en cada reserva se usa para actualizar tabla_clientes si el campo nombre_cliente está vacío

---

## 7. CONTADOR DE RESERVAS

- Al guardar una reserva con estado COMPLETADO:
  - Incrementar en tabla_clientes el contador correspondiente a la rama:
    - DEPORTES → contador_reservas_deportes + 1
    - RESTAURANTE → contador_reservas_restaurante + 1
    - EVENTOS → contador_reservas_eventos + 1

---

## 8. CAMPOS DE ESTADO EN SESIÓN (en memoria durante la conversación)

Todos los campos se almacenan en memoria de sesión (clave: numero_contacto del cliente).
Se persisten mientras la conversación esté activa. Se limpian al llegar a COMPLETADO o CANCELADO.

| Campo                  | Tipo     | Descripción                                                                     |
|------------------------|----------|---------------------------------------------------------------------------------|
| estado_actual          | string   | Estado del bot                                                                  |
| rama_activa            | string   | DEPORTES / RESTAURANTE / EVENTOS / null                                         |
| subtipo_activo         | string   | NINOS / GENERAL_EVT / null (solo rama EVENTOS)                                  |
| paso_actual            | integer  | Índice del paso actual en la secuencia de la rama+subtipo (base 1)             |
| contador_invalidos     | integer  | Intentos inválidos consecutivos en el paso actual                               |
| datos_parciales        | object   | Mapa atributo → valor recolectado hasta el momento                              |
| id_cliente             | integer  | ID del cliente en tabla_clientes                                                |
| timestamp_pausa        | datetime | Timestamp de inicio de pausa (si estado = PAUSADO)                              |
| paso_cambiando         | integer  | Orden del paso que se está editando (solo en CAMBIANDO_DATO)                    |
| fecha_es_futura        | boolean  | true si la fecha de reserva (RESTAURANTE) supera los 7 días desde hoy           |

---

## 9. FLUJO DE CORRECCIÓN DE DATOS (CAMBIAR)

- Trigger: cliente responde `CAMBIAR` en estado CONFIRMACION (solo rama RESTAURANTE)
- Estado: CAMBIANDO_DATO
- Acciones:
  1. Enviar MSG_RES_CAMBIAR con lista de campos modificables
  2. Esperar selección del cliente (respuestas válidas: `1` a `6`, `0`)
  3. `0` → ESCALADO_HUMANO
  4. Selección válida → guardar en `paso_cambiando` el orden del paso correspondiente
  5. Reenviar el mensaje original de ese paso
  6. Esperar nueva respuesta del cliente, aplicar mismas validaciones que en RECOLECTANDO_DATOS
  7. Respuesta válida → actualizar `datos_parciales[atributo]`, volver a estado CONFIRMACION, reenviar MSG_RES_CONFIRMACION con resumen actualizado
  8. Respuesta inválida → aplicar regla de reintentos inválidos (sección 4)
- Correspondencia selección → paso:
  | Selección | nombre_atributo    | Orden paso |
  |-----------|--------------------|------------|
  | 1         | fecha              | 1          |
  | 2         | hora               | 2          |
  | 3         | numero_personas    | 3          |
  | 4         | sector             | 4          |
  | 5         | nombre_responsable | 5          |
  | 6         | mail_contacto      | 6          |

---

## 10. VALIDACIÓN DE INPUTS LIBRES (RAMA EVENTOS)

### 10.1 Fecha — formato DD/MM/AA
- Expresión regular: `^(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/\d{2}$`
- Ejemplos válidos: `15/08/26`, `01/01/27`
- Ante formato inválido: enviar MSG_OPCION_INVALIDA, solicitar nuevamente
- Ante 2 intentos inválidos: ESCALADO_HUMANO

### 10.2 Hora de inicio — formato HH:MM (24 hs)
- Expresión regular: `^([01][0-9]|2[0-3]):[0-5][0-9]$`
- Ejemplos válidos: `20:00`, `08:30`, `23:59`
- Ante formato inválido: enviar MSG_OPCION_INVALIDA, solicitar nuevamente
- Ante 2 intentos inválidos: ESCALADO_HUMANO

### 10.3 Número de niños — entero 1–50 (subtipo NINOS)
- Validar que la respuesta sea un entero positivo
- Rango válido: 1 a 50 inclusive
- Si valor > 50: ESCALADO_HUMANO (motivo: CAPACIDAD_EXCEDIDA), no aplicar regla de reintentos
- Si valor = 0 o no es entero: enviar MSG_OPCION_INVALIDA, aplicar regla de reintentos normales (sección 4)

---

## 11. VALIDACIÓN DE MAIL Y RECORDATORIO (RAMA RESTAURANTE)

### 11.1 Validación de formato
- Expresión regular básica: `^[^@\s]+@[^@\s]+\.[^@\s]+$`
- Ante formato inválido: enviar MSG_RES_MAIL_INVALIDO, solicitar nuevamente
- Ante 2 intentos inválidos: ESCALADO_HUMANO

### 11.2 Regla de fecha futura — RESTAURANTE
- Al recibir la respuesta del paso `fecha` (paso 1, RESTAURANTE):
  - Resolver la opción seleccionada a una fecha calendario concreta (Hoy = fecha actual, Mañana = +1 día, etc.)
  - Calcular diferencia en días entre la fecha seleccionada y hoy
  - Si diferencia ≤ 7: set `fecha_es_futura = false`
  - Si diferencia > 7: set `fecha_es_futura = true`
- `fecha_es_futura` se usa en CONFIRMACION para seleccionar el mensaje correspondiente (sección 2.4)
- Cuando `fecha_es_futura = true` y el cliente confirma con `SI`:
  - Guardar reserva con `estado_reserva = 'PENDIENTE_CONFIRMACION'`
  - Emitir alerta interna al asesor con todos los datos de la reserva y motivo: RESERVA_FUTURA_PENDIENTE

### 11.3 Recordatorio por Google Calendar (a configurar vía API)
- Trigger: reserva confirmada con `SI` en CONFIRMACION, rama RESTAURANTE, mail_contacto presente
- Acción: crear evento en Google Calendar con:
  - Título: `Reserva El Anden — [nombre_responsable]`
  - Fecha y hora: según atributos `fecha` + `hora` de la reserva
  - Invitado: `mail_contacto` del cliente
  - Recordatorio: 2 hs antes del evento
- La integración con Google Calendar API queda pendiente de configuración (credenciales OAuth / Service Account)

---

## 13. LÓGICA DE CÁLCULO DE PRESUPUESTO — SUBTIPO NINOS

### 13.1 Cálculo automático de canchas y coordinadores
Proporcionales a numero_ninos (ver tabla_costos_eventos para precios):
| Rango de niños | Canchas | Coordinadores |
|----------------|---------|---------------|
| 1 – 20         | 1       | 2             |
| 21 – 40        | 2       | 4             |
| 41 – 50        | 3       | 6             |

Guardar en sesión: `num_canchas` y `num_coordinadores` (calculados, no preguntados).

### 13.2 Envío de MSG_EVT_COSTO_MENU (paso INFORMATIVO)
- Trigger: inmediatamente después de guardar `numero_ninos`
- Cálculo: `costo_menu_calculado = numero_ninos × precio_menu_pack_seleccionado`
- Los precios por pack se leen de `tabla_costos_eventos`
- El bot envía MSG_EVT_COSTO_MENU con las variables resueltas
- No espera respuesta. Procede automáticamente al paso siguiente.

### 13.3 Paso condicional numero_adultos / menu_adultos
- Si numero_adultos = 0: saltar paso menu_adultos, registrar `menu_adultos = 0`
- Si numero_adultos > 0: enviar MSG_EVT_MENU_ADULTOS, recolectar `menu_adultos`
- Validar: `menu_adultos` debe ser entero entre 0 y `numero_adultos`

### 13.4 Validación de alimentos_adicionales
- Respuesta válida: uno o más números del 1 al 4 separados por coma, o la palabra `ninguno`
- Ejemplos válidos: `1`, `1,3`, `2,4`, `ninguno`
- Ante formato inválido: enviar MSG_OPCION_INVALIDA, aplicar regla de reintentos (sección 4)
- Guardar como lista de IDs seleccionados (o lista vacía si `ninguno`)

### 13.5 Extras libres (extras_texto)
- Respuesta libre de texto o `ninguno`
- Si el valor es distinto de `ninguno`: marcar `tiene_extras = true` en sesión
- Al guardar la reserva: emitir alerta interna al asesor con los extras anotados (motivo: EXTRAS_PENDIENTES_CONFIRMACION)

### 13.6 Cálculo de presupuesto total
Se calcula en estado CONFIRMACION antes de enviar el resumen. Ver tabla_costos_eventos para todos los valores.

```
Presupuesto estimado:
─────────────────────────────────────────────
Menú niños ({{pack_label}}) × {{numero_ninos}}         = ${{subtotal_menu_ninos}}
Cancha ({{tipo_cancha}}) × {{num_canchas}}             = ${{subtotal_canchas}}
Adicional cancha de caucho (si aplica)                 = ${{subtotal_caucho}}
Coordinadores × {{num_coordinadores}}                  = ${{subtotal_coordinadores}}
Menú adultos × {{menu_adultos}}                        = ${{subtotal_menu_adultos}}
{{alimentos_adicionales_detalle}}                      = ${{subtotal_adicionales}}
─────────────────────────────────────────────
Subtotal                                               = ${{subtotal}}
Recargo feriado (×1.30) (si aplica)                   = ${{recargo_feriado}}
─────────────────────────────────────────────
TOTAL ESTIMADO                                         = ${{total}}
```

### 13.7 Recargo por feriado
- Si `es_feriado = 1`: `total = subtotal × 1.30`
- Si `es_feriado = 2`: `total = subtotal`

---

## 12. ESTADOS DE RESERVA

| estado_reserva           | Cuándo se asigna                                                          |
|--------------------------|---------------------------------------------------------------------------|
| CONFIRMADA               | Cliente confirma con SI, fecha ≤ 7 días (RESTAURANTE) o cualquier EVENTOS|
| PENDIENTE_CONFIRMACION   | Cliente confirma con SI, fecha > 7 días (RESTAURANTE)                     |
| CANCELADA                | Cliente responde NO en CONFIRMACION                                       |
| ESCALADA                 | Flujo derivado a ESCALADO_HUMANO en cualquier punto                       |
