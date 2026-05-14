<?php

namespace App\Jobs;

use App\Models\Reserva;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Reserva $reserva) {}

    public function handle(GoogleCalendarService $calendar): void
    {
        $datos   = $this->reserva->datos ?? [];
        $rama    = $this->reserva->rama_servicio;
        $estado  = $this->reserva->estado_reserva;
        $telefono = $this->reserva->cliente?->numero_contacto;

        [$titulo, $descripcion, $start, $end, $mail] = match ($rama) {
            'RESTAURANTE' => $this->buildRestaurante($datos, $estado, $telefono),
            'EVENTOS'     => $this->buildEvento($datos, $estado, $this->reserva->subtipo, $telefono),
            default       => [null, null, null, null, null],
        };

        if (!$titulo || !$start || !$end) return;

        $calendar->createEvent($titulo, $descripcion, $start, $end, $mail ?: null);
    }

    // ─── Builders ─────────────────────────────────────────────────────────────

    private function buildRestaurante(array $datos, string $estado, ?string $telefono = null): array
    {
        $nombre   = $datos['nombre_responsable'] ?? 'Sin nombre';
        $personas = $datos['numero_personas']    ?? '?';
        $mail     = $datos['mail_contacto']      ?? null;
        $fecha    = $datos['fecha']              ?? null;
        $hora     = $datos['hora']               ?? null;

        if (!$fecha) return [null, null, null, null, null];

        $pendiente = $estado === 'PENDIENTE_CONFIRMACION' ? ' (PENDIENTE)' : '';
        $titulo    = "Reserva Restaurante{$pendiente} — {$nombre} ({$personas})";

        $descripcion = implode("\n", array_filter([
            "Cliente: {$nombre}",
            "Personas: {$personas}",
            $telefono ? "Teléfono: {$telefono}" : null,
            $mail ? "Mail: {$mail}" : null,
        ]));

        $horaStr = $this->resolveHoraRestaurante($hora);
        $carbon  = $this->buildCarbon($fecha, $horaStr ?? '12:00');

        return [$titulo, $descripcion, $carbon->toIso8601String(), $carbon->copy()->addHours(2)->toIso8601String(), $mail];
    }

    private function buildEvento(array $datos, string $estado, ?string $subtipo, ?string $telefono = null): array
    {
        $nombre     = $datos['nombre_responsable'] ?? 'Sin nombre';
        $tipo       = $datos['tipo_evento']        ?? 'Evento';
        $mail       = $datos['mail_contacto']      ?? null;
        $fecha      = $datos['fecha']              ?? null;
        $horaInicio = $datos['hora_inicio']        ?? null;

        if (!$fecha) return [null, null, null, null, null];

        $pendiente = $estado === 'PENDIENTE_CONFIRMACION' ? ' (PENDIENTE)' : '';

        if ($subtipo === 'NINOS') {
            $ninos  = $datos['numero_ninos'] ?? '?';
            $titulo = "Cumpleaños Niños{$pendiente} — {$nombre} ({$ninos} niños)";

            $descripcion = implode("\n", array_filter([
                "Responsable: {$nombre}",
                "Niños: {$ninos}",
                "Pack: " . ($datos['pack_seleccionado'] ?? '?'),
                isset($datos['numero_adultos']) ? "Adultos: {$datos['numero_adultos']}" : null,
                $telefono ? "Teléfono: {$telefono}" : null,
                $mail ? "Mail: {$mail}" : null,
            ]));

            $horaStr = is_int($horaInicio) || ctype_digit((string)$horaInicio)
                ? sprintf('%02d:00', (int)$horaInicio)
                : ($horaInicio ?? '12:00');

            $carbon = $this->buildCarbon($fecha, $horaStr);
            return [$titulo, $descripcion, $carbon->toIso8601String(), $carbon->copy()->addMinutes(150)->toIso8601String(), $mail];
        }

        // GENERAL_EVT
        $personas  = $datos['numero_personas'] ?? '?';
        $titulo    = "{$tipo}{$pendiente} — {$nombre} ({$personas} personas)";

        $descripcion = implode("\n", array_filter([
            "Responsable: {$nombre}",
            "Personas: {$personas}",
            $telefono ? "Teléfono: {$telefono}" : null,
            $mail ? "Mail: {$mail}" : null,
        ]));

        $horaStr = $horaInicio ?? '12:00';
        $carbon  = $this->buildCarbon($fecha, $horaStr);
        return [$titulo, $descripcion, $carbon->toIso8601String(), $carbon->copy()->addHours(3)->toIso8601String(), $mail];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildCarbon(string $fecha, string $hora): Carbon
    {
        // $fecha: 'd/m/y' → e.g. "15/08/26"
        // $hora: 'HH:MM'  → e.g. "20:00"
        $carbon = Carbon::createFromFormat('d/m/y', $fecha, config('app.timezone', 'America/Argentina/Buenos_Aires'));
        [$h, $m] = array_map('intval', explode(':', $hora));
        return $carbon->setTime($h, $m);
    }

    private function resolveHoraRestaurante(?string $stored): ?string
    {
        if (!$stored) return null;

        // El valor guardado es el label del mensaje: "Turno 1: 12.30 hs", "Turno 2: 14 hs", etc.
        $clean = preg_replace('/^Turno\s+\d+:\s*/i', '', $stored);
        $clean = str_replace(['.', 'hs', 'h', ' '], [':', '', '', ''], trim($clean));

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $clean, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }
        if (preg_match('/^(\d{1,2})$/', $clean, $m)) {
            return sprintf('%02d:00', (int)$m[1]);
        }
        return null;
    }
}
