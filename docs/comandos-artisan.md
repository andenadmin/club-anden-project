# Comandos Artisan — Club El Andén

Referencia de todos los comandos personalizados disponibles en el proyecto.

---

## Reservas

### `reservas:maximizar`

Crea reservas de prueba hasta alcanzar el **umbral de alerta de capacidad** (`sector_alerta_pct`, por defecto 70%) en cada sector para una fecha específica. Al terminar dispara las notificaciones de panel, por lo que los dialogs de "sin cupo" aparecen automáticamente en el admin.

```bash
# Llenar todos los sectores al 70% para hoy
php artisan reservas:maximizar

# Llenar todos los sectores para una fecha específica
php artisan reservas:maximizar 31/12/2026

# Llenar solo un sector
php artisan reservas:maximizar --sector=salon
php artisan reservas:maximizar --sector=galeria
php artisan reservas:maximizar --sector=terraza
php artisan reservas:maximizar --sector=parrilla

# Controlar cuántas personas por reserva (default: 2)
php artisan reservas:maximizar --sector=salon --personas=4
```

**Cómo funciona:**
- Calcula cuántas personas faltan para llegar al 70% de capacidad de cada sector.
- Crea reservas `CONFIRMADA` distribuidas en distintos horarios con datos de prueba.
- Llama a `checkAlertaOcupacion()` al final → genera las `PanelNotification` de tipo `sector_alerta` que abren el dialog en el panel.
- Si el sector ya está al 70% o más, lo indica sin crear nada.

**Para borrar las reservas de prueba:** `php artisan reservas:limpiar --confirmar`

---

### `reservas:limpiar`

Borra reservas de prueba. **Por defecto solo hace un dry-run** — no toca nada hasta que se agregue `--confirmar`.

```bash
# Ver qué se borraría (no hace nada)
php artisan reservas:limpiar

# Borrar solo las reservas (resetea contadores de clientes)
php artisan reservas:limpiar --confirmar

# Ver también cuántos clientes, sesiones y mensajes se borrarían
php artisan reservas:limpiar --todo

# Borrar todo: reservas + clientes + sesiones de bot + mensajes
php artisan reservas:limpiar --todo --confirmar

# Filtrar por número de contacto (parcial)
php artisan reservas:limpiar --numero=5491155 --confirmar
```

| Flag | Descripción |
|------|-------------|
| `--confirmar` | Ejecuta el borrado real. Sin este flag es siempre dry-run. |
| `--todo` | Incluye también clientes, sesiones de bot y mensajes de conversación. |
| `--numero=X` | Limita el borrado a registros de un número de contacto (búsqueda parcial). |

---

### `reservas:auto-confirm-restaurante`

Corre cada 4 horas (scheduler). Avisa al asesor sobre reservas de restaurante en estado `PENDIENTE_CONFIRMACION`. A la quinta ejecución (≈ 20 horas sin confirmar), las confirma automáticamente y notifica al cliente por WhatsApp.

```bash
php artisan reservas:auto-confirm-restaurante
```

---

## Inbox

### `inbox:sweep-takeovers`

Resetea conversaciones donde un asesor tomó el control (`ASESOR_TAKEOVER`) pero lleva más de 12 horas sin responder. Vuelve la sesión a INICIO y manda `MSG_TIMEOUT_ASESOR` al usuario.

```bash
php artisan inbox:sweep-takeovers

# Cap personalizado
php artisan inbox:sweep-takeovers --cap-hours=6
```

---

## Feriados

### `feriados:sync`

Sincroniza los feriados nacionales argentinos desde la API pública de [argentinadatos.com](https://argentinadatos.com). El bot usa estos datos para avisar cuando una fecha cae en feriado.

```bash
php artisan feriados:sync

php artisan feriados:sync 2027
```

Correrlo al inicio del año o cuando se agreguen feriados extraordinarios.
