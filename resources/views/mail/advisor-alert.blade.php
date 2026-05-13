@component('mail::message')
# Asesor requerido en Andy Bot 🤖

Un cliente necesita atención. Por favor revisá la conversación lo antes posible.

---

**Cliente:** {{ $nombreCliente }}
**Número:** {{ $numeroContacto }}
**Motivo:** {{ $motivoLabel }}
**Sección:** {{ $ramaActiva }}

@component('mail::button', ['url' => $inboxUrl, 'color' => 'green'])
Ver conversación en Inbox
@endcomponent

Si el cliente resuelve la situación por su cuenta, el bot se reactivará automáticamente luego de 12 horas de inactividad.

Saludos,
**Andy Bot — Club El Andén**
@endcomponent
