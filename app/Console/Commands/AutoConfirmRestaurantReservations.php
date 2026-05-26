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

    protected $description = 'Cada 4hs: avisa al asesor de reservas pendientes. A la 5ta notificación (20hs) las auto-confirma.';

    private const MAX_AVISOS = 5;

    public function handle(WhatsAppClient $whatsapp): int
    {
        try {
            return $this->process($whatsapp);
        } catch (Throwable $e) {
            report($e);

            // Notificación de error visible en el panel para los asesores
            try {
                PanelNotification::create([
                    'tipo'    => 'job_error',
                    'payload' => [
                        'mensaje' => "🚨 *El job de auto-confirmación de reservas falló.* Revisá los logs del servidor para corregirlo.\n\nError: " . $e->getMessage(),
                    ],
                    'leida' => false,
                ]);
            } catch (Throwable) {
                // Si tampoco podemos crear la notificación, al menos queda en el log
            }

            return self::FAILURE;
        }
    }

    private function process(WhatsAppClient $whatsapp): int
    {
        $candidates = Reserva::where('rama_servicio', 'RESTAURANTE')
            ->where('estado_reserva', 'PENDIENTE_CONFIRMACION')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Sin reservas de restaurante pendientes de confirmación.');
            return self::SUCCESS;
        }

        $paraAvisar      = [];
        $autoConfirmadas = [];

        foreach ($candidates as $reserva) {
            try {
                $datos   = $reserva->datos ?? [];
                $avisos  = (int) ($datos['aviso_pendiente_count'] ?? 0);
                $avisos++;

                if ($avisos < self::MAX_AVISOS) {
                    // Todavía dentro del período de aviso
                    $datos['aviso_pendiente_count'] = $avisos;
                    $reserva->datos = $datos;
                    $reserva->save();

                    $paraAvisar[] = $reserva;
                    $this->line("  · Reserva #{$reserva->id} — aviso #{$avisos}.");
                } else {
                    // 5to aviso → auto-confirmar
                    $datos['aviso_pendiente_count'] = $avisos;
                    $reserva->datos                 = $datos;
                    $reserva->estado_reserva        = 'CONFIRMADA';
                    $reserva->save();

                    $autoConfirmadas[] = $reserva;
                    $this->line("  · Reserva #{$reserva->id} — auto-confirmada (aviso #{$avisos}).");

                    // Notificar al cliente por WhatsApp
                    $cliente = $reserva->cliente;
                    if ($cliente && $cliente->numero_contacto) {
                        $fechaStr = $datos['fecha'] ?? '';
                        $hora     = $datos['hora']  ?? '';
                        try {
                            $fechaCarbon = Carbon::createFromFormat('d/m/y', $fechaStr);
                            $fechaFmt    = $fechaCarbon ? $fechaCarbon->translatedFormat('l j \d\e F') : $fechaStr;
                        } catch (Throwable) {
                            $fechaFmt = $fechaStr;
                        }
                        $msg = "✅ ¡Tu reserva en El Andén está confirmada! Te esperamos el *{$fechaFmt}* a las *{$hora}*. 🌿\nAnte cualquier cambio, serás contactado por un asesor.";
                        try {
                            $whatsapp->sendText($cliente->numero_contacto, $msg);
                        } catch (MetaApiException $e) {
                            report($e);
                            $this->warn("  · No se pudo enviar WhatsApp a {$cliente->numero_contacto}: {$e->getMessage()}");
                        }
                    }
                }
            } catch (Throwable $e) {
                report($e);
                $this->error("  · Error al procesar reserva #{$reserva->id}: {$e->getMessage()}");
            }
        }

        // Notificación de aviso al panel (para que el asesor confirme manualmente)
        if (count($paraAvisar) > 0) {
            $ids = implode(', ', array_map(fn($r) => "#{$r->id}", $paraAvisar));
            PanelNotification::create([
                'tipo'    => 'aviso_confirmar',
                'payload' => [
                    'mensaje'  => "⏳ Tenés " . count($paraAvisar) . " reserva(s) de restaurante pendiente(s) de confirmación manual ({$ids}). Si no se confirman, se harán automáticamente en la próxima ejecución.",
                    'cantidad' => count($paraAvisar),
                ],
                'leida' => false,
            ]);
        }

        // Notificación de auto-confirmación forzada
        if (count($autoConfirmadas) > 0) {
            $ids = implode(', ', array_map(fn($r) => "#{$r->id}", $autoConfirmadas));
            PanelNotification::create([
                'tipo'    => 'auto_confirm',
                'payload' => [
                    'mensaje'  => "✅ Se confirmaron automáticamente " . count($autoConfirmadas) . " reserva(s) de restaurante tras 20 hs sin confirmación manual ({$ids}).",
                    'cantidad' => count($autoConfirmadas),
                ],
                'leida' => false,
            ]);
        }

        $this->info('Job finalizado. Avisos: ' . count($paraAvisar) . '. Auto-confirmadas: ' . count($autoConfirmadas) . '.');
        return self::SUCCESS;
    }
}
