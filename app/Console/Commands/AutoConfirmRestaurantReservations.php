<?php

namespace App\Console\Commands;

use App\Models\PanelNotification;
use App\Models\Reserva;
use App\Services\Meta\WhatsAppClient;
use App\Services\Meta\MetaApiException;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class AutoConfirmRestaurantReservations extends Command
{
    protected $signature = 'reservas:auto-confirm-restaurante';

    protected $description = 'Auto-confirma reservas de restaurante PENDIENTE_CONFIRMACION cuya fecha cae dentro de las próximas 24 hs.';

    public function handle(WhatsAppClient $whatsapp): int
    {
        $now      = Carbon::now();
        $cutoff   = $now->copy()->addHours(24);

        // Traer todas las reservas PENDIENTE_CONFIRMACION de restaurante.
        // La fecha está en datos->fecha con formato d/m/y (ej. "25/05/26").
        $candidates = Reserva::where('rama_servicio', 'RESTAURANTE')
            ->where('estado_reserva', 'PENDIENTE_CONFIRMACION')
            ->get();

        $confirmed = 0;

        foreach ($candidates as $reserva) {
            try {
                $fechaStr = $reserva->datos['fecha'] ?? null;
                if (!$fechaStr) {
                    continue;
                }

                // Parsear formato d/m/y → Carbon
                $fechaReserva = Carbon::createFromFormat('d/m/y', $fechaStr);
                if (!$fechaReserva) {
                    continue;
                }

                // Fecha + hora para comparar contra las próximas 24 hs
                $horaStr = $reserva->datos['hora'] ?? '00:00 hs';
                $horaLimpia = preg_replace('/[^0-9:]/', '', $horaStr); // "20:00 hs" → "20:00"
                [$h, $m] = array_pad(explode(':', $horaLimpia), 2, '00');
                $fechaReserva->setTime((int) $h, (int) $m, 0);

                if ($fechaReserva->lt($now) || $fechaReserva->gt($cutoff)) {
                    continue;
                }

                // Confirmar la reserva
                $reserva->update(['estado_reserva' => 'CONFIRMADA']);
                $confirmed++;

                // Notificar al cliente por WhatsApp
                $cliente = $reserva->cliente;
                if ($cliente && $cliente->numero_contacto) {
                    $fechaFormateada = $fechaReserva->translatedFormat('l j \d\e F');
                    $hora            = $reserva->datos['hora'] ?? '';
                    $msg = "✅ ¡Tu reserva en El Andén está confirmada! Te esperamos el *{$fechaFormateada}* a las *{$hora}*. 🌿\nAnte cualquier cambio o modificación, serás contactado por un asesor.";

                    try {
                        $whatsapp->sendText($cliente->numero_contacto, $msg);
                    } catch (MetaApiException $e) {
                        report($e);
                        $this->warn("  · No se pudo enviar WhatsApp a {$cliente->numero_contacto}: {$e->getMessage()}");
                    }
                }

                $this->line("  · Reserva #{$reserva->id} confirmada ({$fechaStr} {$hora}).");
            } catch (Throwable $e) {
                report($e);
                $this->error("  · Error al procesar reserva #{$reserva->id}: {$e->getMessage()}");
            }
        }

        if ($confirmed === 0) {
            $this->info('Sin reservas de restaurante para auto-confirmar.');
            return self::SUCCESS;
        }

        // Notificación resumen en panel
        PanelNotification::create([
            'tipo'    => 'auto_confirm',
            'payload' => [
                'mensaje' => "✅ Se confirmaron automáticamente {$confirmed} reserva(s) de restaurante para las próximas 24 hs.",
                'cantidad' => $confirmed,
            ],
            'leida' => false,
        ]);

        $this->info("Auto-confirmadas {$confirmed} reserva(s) de restaurante. Notificación de panel creada.");

        return self::SUCCESS;
    }
}
