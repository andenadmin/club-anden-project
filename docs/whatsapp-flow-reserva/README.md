# WhatsApp Flow de reserva de restaurante

Reemplaza el ida y vuelta de mensajes de la reserva de restaurante por un Flow con endpoint.
Una reserva completa usa **2 mensajes salientes** (el que abre el Flow y la confirmación en texto libre).

| Pieza | Archivo |
|---|---|
| Flow JSON (v7.3) | `resources/whatsapp-flows/reserva_restaurante.json` |
| Endpoint HTTP | `app/Http/Controllers/WhatsAppFlowController.php` → `POST /api/whatsapp-flows/reserva` |
| Cifrado | `app/Services/WhatsAppFlows/FlowCrypto.php` |
| Lógica de pantallas | `app/Services/WhatsAppFlows/ReservaFlowService.php` |
| Disponibilidad por turno | `app/Services/RestaurantTurnos.php`, `app/Services/Turno.php` |
| Mensajes del chat | `app/Services/WhatsAppFlows/ReservaFlowMessenger.php` |
| Tokens del Flow | tabla `whatsapp_flow_tokens` |
| Configuración | `config/whatsapp_flows.php` |

## Variables de entorno

```dotenv
WA_RESERVA_FLOW_ENABLED=false      # true = Flow, false = flujo viejo por texto
WA_FLOW_RESERVA_ID=                # ID del Flow en WhatsApp Manager
WA_FLOW_RESERVA_MODE=published     # "draft" para probar sin publicar
WA_FLOW_PRIVATE_KEY=               # PEM de la clave privada en base64 (lo imprime el comando de abajo)
WA_FLOW_PRIVATE_KEY_PASSPHRASE=    # solo si la clave tiene contraseña
```

El endpoint valida la firma `X-Hub-Signature-256` con el `WHATSAPP_APP_SECRET` que ya existe.

**Rollback:** `WA_RESERVA_FLOW_ENABLED=false` y `php artisan config:clear`. El bot vuelve al flujo por texto de inmediato.

## Paso a paso

### 1. Migración y dependencia

```bash
composer install              # instala phpseclib/phpseclib
php artisan migrate           # crea whatsapp_flow_tokens
```

### 2. Generar el par de claves

```bash
php artisan whatsapp-flows:generar-claves
# opcional: --passphrase=... para proteger la privada
```

Deja `private.pem` y `public.pem` en `storage/app/private/whatsapp-flows/` (ignorado por git) e imprime la línea
`WA_FLOW_PRIVATE_KEY=...` para pegar en el `.env` del servidor. Nunca subas `private.pem` al repo.

Alternativa con OpenSSL (lo que sugiere Meta):

```bash
openssl genrsa -des3 -out private.pem 2048
openssl rsa -in private.pem -outform PEM -pubout -out public.pem
```

### 3. Subir la clave pública al número

Hay que hacerlo **en cada número** que vaya a mandar el Flow:

```bash
curl -X POST "https://graph.facebook.com/v25.0/$PHONE_NUMBER_ID/whatsapp_business_encryption" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "business_public_key=$(cat storage/app/private/whatsapp-flows/public.pem)"

# Verificar: business_public_key_signature_status debe ser VALID
curl "https://graph.facebook.com/v25.0/$PHONE_NUMBER_ID/whatsapp_business_encryption" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

### 4. Crear el Flow

**Opción A: WhatsApp Manager**
1. WhatsApp Manager → Cuenta → **Flows** → **Crear Flow**. Nombre: `reserva_restaurante`, categoría **Reserva de citas** (APPOINTMENT_BOOKING), plantilla "Sin plantilla".
2. En el editor, pegá el contenido de `resources/whatsapp-flows/reserva_restaurante.json` y guardá. El panel de la derecha muestra los errores de validación, si los hay.
3. Menú **⋯ → Configuración del endpoint**: URI `https://<tu-dominio>/api/whatsapp-flows/reserva`, elegí el número y la app de Meta.
4. Usá **Verificar el estado de salud del endpoint**: Meta manda un `ping` cifrado y tiene que dar verde.

**Opción B: API**

```bash
# Crear (queda en DRAFT)
curl -X POST "https://graph.facebook.com/v25.0/$WABA_ID/flows" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -F 'name=reserva_restaurante' \
  -F 'categories=["APPOINTMENT_BOOKING"]' \
  -F "endpoint_uri=https://<tu-dominio>/api/whatsapp-flows/reserva" \
  -F "flow_json=$(cat resources/whatsapp-flows/reserva_restaurante.json)"
# → {"id": "<FLOW_ID>", "validation_errors": [...]}

# Actualizar el JSON después de un cambio
curl -X POST "https://graph.facebook.com/v25.0/$FLOW_ID/assets" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -F "file=@resources/whatsapp-flows/reserva_restaurante.json" \
  -F 'name=flow.json' -F 'asset_type=FLOW_JSON'
```

Guardá el ID en `WA_FLOW_RESERVA_ID`.

### 5. Probar sin publicar

1. **Preview:**
   ```bash
   curl "https://graph.facebook.com/v25.0/$FLOW_ID?fields=preview.invalidate(false)" \
     -H "Authorization: Bearer $ACCESS_TOKEN"
   # → {"preview": {"preview_url": "https://business.facebook.com/wa/manage/flows/...", "expires_at": "..."}}
   ```
   El `preview_url` dura 30 días y se abre en el navegador o en el celular. Con endpoint, la preview sirve para ver el diseño, pero no pasa por nuestro `flow_token`, así que el INIT devuelve 427. Para probar el recorrido completo hay que usar el paso 2.
2. **En un celular de prueba:** en un entorno de staging, con `WA_RESERVA_FLOW_ENABLED=true` y `WA_FLOW_RESERVA_MODE=draft`, escribile al número desde un teléfono de prueba. El bot manda el Flow en modo borrador y se puede recorrer completo: fechas, turno lleno, confirmación y mensaje final.

   Casos a probar:
   - Día de semana, turno de 12 hs.
   - Sábado después de las 11.
   - 15 o más personas.
   - Turno que se llena mientras el cliente completa el formulario (reservá el cupo desde otro número antes de confirmar).
   - Sin aceptar los T&C.

### 6. Publicar

```bash
curl -X POST "https://graph.facebook.com/v25.0/$FLOW_ID/publish" -H "Authorization: Bearer $ACCESS_TOKEN"
```

Un Flow publicado **no se puede editar**: los cambios se hacen clonándolo (`clone_flow_id`) y cambiando `WA_FLOW_RESERVA_ID`.
Después: `WA_FLOW_RESERVA_MODE=published`, `WA_RESERVA_FLOW_ENABLED=true`, `php artisan config:clear`.

### 7. Monitoreo

- Logs `[FLOW][ENDPOINT]` y `[FLOW][RESERVA]` (firma inválida, fallas de descifrado, errores que reporta WhatsApp).
- Si el endpoint no responde o devuelve errores, Meta puede pasar el Flow a THROTTLED o BLOCKED. Revisá el estado de salud en WhatsApp Manager.
- 421 significa que la clave privada no coincide con la pública subida al número: repetir los pasos 2 y 3.

## Capacidad: cómo se calcula

- **Cupo por sector y turno** = `floor(capacidad × capacidad_pct / 100)`. El 70% se configura en `capacidad_pct` desde el panel. Si hay override por fecha, se usa ese.
- **Turnos superpuestos:** un turno con "hasta las X hs" en el texto (panel → opciones de horario) ocupa la mesa hasta esa hora. Por eso el de 12 hs (hasta las 16) descuenta del de 11:45 y del de 14. Los turnos sin hora de fin solo chocan con lo que está activo a su hora de inicio.
- **"Sin preferencia":** aparece si algún sector tiene lugar y si la ocupación total del turno cabe en la capacidad máxima del restaurante (100%, suma de sectores abiertos).
- **Reservas:** se toman de hoy a hoy + 7 días. Sábados, domingos y feriados, el mismo día después de las 11:00 no se reserva (orden de llegada).
