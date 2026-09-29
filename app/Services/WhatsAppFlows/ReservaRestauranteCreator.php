<?php

namespace App\Services\WhatsAppFlows;

use App\Jobs\CreateCalendarEvent;
use App\Models\BotSession;
use App\Models\Cliente;
use App\Models\Reserva;
use App\Services\RestaurantCapacity;
use Carbon\Carbon;

/**
 * Misma lógica que BotEngine::confirmarReserva() para la rama RESTAURANTE
 * (estado, alerta de ocupación, contadores, CRM y evento de calendario).
 */
class ReservaRestauranteCreator
{
    public function crear(BotSession $session, Cliente $cliente, array $datos): Reserva
    {
        $fecha    = Carbon::createFromFormat('!d/m/y', $datos['fecha']);
        $esFutura = $fecha->diffInDays(Carbon::today(), false) < -7;
        $datos['fecha_es_futura'] = $esFutura;

        $reserva = Reserva::create([
            'id_cliente'        => $cliente->id,
            'rama_servicio'     => 'RESTAURANTE',
            'subtipo'           => null,
            'estado_reserva'    => $esFutura ? 'PENDIENTE_CONFIRMACION' : 'CONFIRMADA',
            'datos'             => $datos,
            'tiene_extras'      => false,
            'presupuesto_total' => 0,
        ]);

        if (!empty($datos['sector_key'])) {
            RestaurantCapacity::checkAlertaOcupacion($datos['sector_key'], $datos['fecha']);
        }

        $cliente->increment('contador_reservas_restaurante');
        $cliente->crmOrCreate()->update(['fecha_ultimo_evento' => now()->toDateString()]);

        $session->mergeEstado([
            'estado_actual'      => 'COMPLETADO',
            'rama_activa'        => 'RESTAURANTE',
            'subtipo_activo'     => null,
            'current_step'       => null,
            'datos_parciales'    => $datos,
            'contador_invalidos' => 0,
        ]);

        if (config('services.google.calendar_id')) {
            CreateCalendarEvent::dispatch($reserva);
        }

        return $reserva;
    }
}
