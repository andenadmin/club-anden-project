<?php

namespace App\Console\Commands;

use App\Models\BotSession;
use App\Models\PanelNotification;
use App\Models\Reserva;
use App\Services\Meta\WhatsAppClientFactory;
use App\Services\Meta\MetaApiException;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class EnviarRecordatoriosEventos extends Command
{
    protected $signature = 'reservas:recordatorio-eventos {--horas=48 : Horas de anticipación para enviar el recordatorio}';

    protected $description = 'Envía el template recordatorio_evento a clientes con eventos próximos que aún no recibieron recordatorio.';

    public function handle(WhatsAppClientFactory $factory): int
    {
        try {
            return $this->process($factory);
        } catch (Throwable $e) {
            report($e);
            try {
                PanelNotification::create([
                    'tipo'    => 'job_error',
                    'payload' => [
                        'mensaje' => "Error en job de recordatorios de eventos.\nError: " . $e->getMessage(),
                    ],
                    'leida' => false,
                ]);
            } catch (Throwable) {}

            return self::FAILURE;
        }
    }

    private function process(WhatsAppClientFactory $factory): int
    {
        $horas       = (int) $this->option('horas');
        $desde       = Carbon::now();
        $hasta       = Carbon::now()->addHours($horas);
        $enviados    = 0;
        $omitidos    = 0;
        $errores     = 0;

        $reservas = Reserva::whereIn('rama_servicio', ['EVENTOS'])
            ->whereIn('estado_reserva', ['CONFIRMADA', 'PENDIENTE_CONFIRMACION'])
            ->with('cliente')
            ->get();

        foreach ($reservas as $reserva) {
            try {
                $datos = $reserva->datos ?? [];

                // Saltar si ya se envió recordatorio
                if (!empty($datos['recordatorio_enviado'])) {
                    $omitidos++;
                    continue;
                }

                $fechaStr = $datos['fecha'] ?? null;
                if (!$fechaStr) {
                    $omitidos++;
                    continue;
                }

                try {
                    $fecha = Carbon::createFromFormat('d/m/y', $fechaStr);
                } catch (Throwable) {
                    $omitidos++;
                    continue;
                }

                if (!$fecha || !$fecha->between($desde, $hasta)) {
                    $omitidos++;
                    continue;
                }

                $cliente = $reserva->cliente;
                if (!$cliente || !$cliente->numero_contacto) {
                    $omitidos++;
                    continue;
                }

                $session = BotSession::where('numero_contacto', $cliente->numero_contacto)->first();
                if (!$session) {
                    $omitidos++;
                    continue;
                }

                $to     = PhoneNumber::normalize($cliente->numero_contacto);
                $client = $factory->forSession($session);

                $nombre      = $cliente->nombre_cliente ?? 'cliente';
                $tipoEvento  = $datos['tipo_evento'] ?? 'tu evento';
                $fechaFmt    = $fecha->translatedFormat('j \d\e F');

                $client->sendTemplate($to, 'recordatorio_evento', 'es_AR', [
                    'nombre'  => $nombre,
                    'evento'  => $tipoEvento,
                    'fecha'   => $fechaFmt,
                ]);

                // Marcar recordatorio como enviado para no duplicar
                $datos['recordatorio_enviado'] = Carbon::now()->toIso8601String();
                $reserva->datos = $datos;
                $reserva->save();

                $enviados++;
                $this->line("  · Recordatorio enviado → Reserva #{$reserva->id} ({$nombre}, {$fechaFmt}).");

            } catch (MetaApiException $e) {
                report($e);
                $errores++;
                $this->warn("  · Error Meta al enviar reserva #{$reserva->id}: {$e->getMessage()}");
            } catch (Throwable $e) {
                report($e);
                $errores++;
                $this->error("  · Error al procesar reserva #{$reserva->id}: {$e->getMessage()}");
            }
        }

        if ($enviados > 0) {
            PanelNotification::create([
                'tipo'    => 'recordatorio_enviado',
                'payload' => [
                    'mensaje'  => "Se enviaron {$enviados} recordatorio(s) de eventos para las próximas {$horas} hs.",
                    'cantidad' => $enviados,
                ],
                'leida' => false,
            ]);
        }

        $this->info("Recordatorios: enviados={$enviados}, omitidos={$omitidos}, errores={$errores}.");
        return self::SUCCESS;
    }
}
