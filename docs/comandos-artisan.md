# Comandos Artisan — Club El Andén

Referencia de todos los comandos personalizados disponibles en el proyecto.

---

## Reservas

### `reservas:maximizar`

Permite aceptar reservas hasta la **capacidad máxima real** de cada sector para un día específico, ignorando el porcentaje de capacidad (`capacidad_pct`) configurado globalmente.

Útil cuando hay un evento especial y se quiere abrir al máximo sin tocar la configuración global que afecta todos los días.

```bash
# Maximizar para hoy
php artisan reservas:maximizar

# Maximizar para una fecha específica
php artisan reservas:maximizar 31/12/2026
```

**Cómo funciona:**
- Crea un registro en la tabla `restaurant_capacity_overrides` para esa fecha con los valores brutos de cada sector (sin aplicar el porcentaje).
- Cuando el bot verifica si hay cupo disponible, primero mira si existe un override para esa fecha. Si existe, usa ese límite en lugar del global.
- No modifica la configuración global — el resto de los días se mantienen igual.

**Ejemplo:** si la config global tiene Salón = 40 personas al 80%, normalmente el límite es 32. Con este comando, el límite pasa a 40 para ese día.

---

### `reservas:resetear`

Elimina el override de capacidad máxima para una fecha, volviendo al límite global con `capacidad_pct` aplicado.

```bash
# Resetear hoy
php artisan reservas:resetear

# Resetear una fecha específica
php artisan reservas:resetear 31/12/2026
```

---

### `reservas:limpiar`

Borra reservas de prueba. **Por defecto solo hace un dry-run** y muestra lo que se borraría — no toca nada hasta que se agregue `--confirmar`.

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

**Flags disponibles:**

| Flag | Descripción |
|------|-------------|
| `--confirmar` | Ejecuta el borrado real. Sin este flag es siempre dry-run. |
| `--todo` | Incluye también clientes, sesiones de bot y mensajes de conversación. |
| `--numero=X` | Limita el borrado a registros asociados a un número de contacto (búsqueda parcial). |

**Nota:** cuando se borran solo reservas (sin `--todo`), los contadores de reservas en la tabla `clientes` se resetean a 0.

---

### `reservas:auto-confirm-restaurante`

Corre cada 4 horas (configurado en el scheduler). Avisa al asesor sobre reservas de restaurante que llevan tiempo en estado `PENDIENTE_CONFIRMACION`. A la quinta ejecución (≈ 20 horas), las confirma automáticamente y notifica al cliente por WhatsApp.

```bash
php artisan reservas:auto-confirm-restaurante
```

**Cómo funciona:**
- Busca todas las reservas de restaurante en estado `PENDIENTE_CONFIRMACION`.
- Incrementa un contador interno (`aviso_pendiente_count`) en el campo `datos` de cada reserva.
- Crea una notificación en el panel de administración para que el asesor confirme manualmente.
- Al llegar al 5to aviso, cambia el estado a `CONFIRMADA` y envía un mensaje de confirmación al cliente por WhatsApp.

---

## Inbox

### `inbox:sweep-takeovers`

Aplica el cap automático sobre conversaciones donde un asesor tomó el control (`ASESOR_TAKEOVER`) pero lleva más de 12 horas sin responder. Resetea la conversación al inicio y le manda al usuario un mensaje de disculpas (`MSG_TIMEOUT_ASESOR`).

```bash
# Ejecutar con el cap por defecto (12 horas)
php artisan inbox:sweep-takeovers

# Ejecutar con un cap personalizado
php artisan inbox:sweep-takeovers --cap-hours=6
```

**Flags disponibles:**

| Flag | Descripción |
|------|-------------|
| `--cap-hours=N` | Horas a partir de las cuales se fuerza el reset (default: 12). |

---

## Feriados

### `feriados:sync`

Sincroniza los feriados nacionales argentinos desde la API pública de [argentinadatos.com](https://argentinadatos.com). El bot usa estos datos para avisar cuando una fecha elegida cae en feriado.

```bash
# Sincronizar el año actual
php artisan feriados:sync

# Sincronizar un año específico
php artisan feriados:sync 2027
```

**Cuándo correrlo:** al inicio del año o cuando se agreguen feriados extraordinarios. Se puede automatizar en el scheduler.
