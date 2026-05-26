<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservasController extends Controller
{
    public function index(Request $request): Response
    {
        $vista  = $request->input('vista', 'dia');
        $fecha  = $request->input('fecha', Carbon::today()->format('Y-m-d'));
        $inicio = Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay();

        $dias = match ($vista) {
            'semana'   => 7,
            'quincena' => 15,
            default    => 1,
        };

        $fechasRango     = collect(range(0, $dias - 1))->map(fn ($d) => $inicio->copy()->addDays($d));
        $fechasBotFormat = $fechasRango->map(fn ($f) => $f->format('d/m/y'))->values()->toArray();
        $placeholders    = implode(',', array_fill(0, count($fechasBotFormat), '?'));

        $reservas = Reserva::with('cliente')
            ->whereIn('rama_servicio', ['RESTAURANTE', 'EVENTOS'])
            ->whereNotIn('estado_reserva', ['CANCELADA'])
            ->whereRaw("json_extract(datos, '$.fecha') IN ({$placeholders})", $fechasBotFormat)
            ->get()
            ->map(function (Reserva $r) {
                $datos = $r->datos ?? [];

                $tipo = match (true) {
                    $r->rama_servicio === 'RESTAURANTE'                  => 'RESTAURANTE',
                    in_array($r->subtipo, ['NINOS', 'FUTBOL'], true)     => 'FUTBOL',
                    $r->subtipo === 'PADEL'                              => 'PADEL',
                    $r->subtipo === 'HOCKEY'                             => 'HOCKEY',
                    default                                              => 'GENERAL_EVT',
                };

                // Hora normalizada a "HH:mm"
                $hora = null;
                if ($tipo === 'RESTAURANTE') {
                    $hora = $datos['hora'] ?? null;
                } else {
                    $horaInicio = $datos['hora_inicio'] ?? null;
                    if (isset($horaInicio) && is_numeric($horaInicio) && !str_contains((string) $horaInicio, ':')) {
                        $hora = sprintf('%02d:00', (int) $horaInicio);
                    } else {
                        $hora = $horaInicio;
                    }
                }
                // Normalizar a "HH:MM" (soporta labels como "Turno noche 1: 20 hs" o "11.30 hs")
                if ($hora) {
                    $h = (string) $hora;
                    if (preg_match('/(\d{1,2})[.:,](\d{2})\s*hs/i', $h, $hm)) {
                        $hora = sprintf('%02d:%02d', (int) $hm[1], (int) $hm[2]);
                    } elseif (preg_match('/(\d{1,2})\s*hs/i', $h, $hm)) {
                        $hora = sprintf('%02d:00', (int) $hm[1]);
                    } else {
                        $h = str_replace('.', ':', $h);
                        if (preg_match('/^(\d{1,2}):(\d{2})$/', $h, $hm)) {
                            $hora = sprintf('%02d:%02d', (int) $hm[1], (int) $hm[2]);
                        } elseif (preg_match('/(\d{1,2}):(\d{2})/', $h, $hm)) {
                            $hora = sprintf('%02d:%02d', (int) $hm[1], (int) $hm[2]);
                        }
                    }
                }

                // Personas normalizadas
                if (in_array($tipo, ['FUTBOL', 'PADEL', 'HOCKEY'], true)) {
                    $ninos   = (int) ($datos['numero_ninos'] ?? 0);
                    $adultos = (int) ($datos['numero_adultos'] ?? 0);
                    $personas = ($ninos + $adultos) . ' pers.';
                } else {
                    $personas = (string) ($datos['numero_personas'] ?? '');
                }

                // Fecha normalizada a "Y-m-d"
                $fechaNorm = null;
                try {
                    $fechaBot  = $datos['fecha'] ?? '';
                    $fechaNorm = $fechaBot
                        ? Carbon::createFromFormat('d/m/y', $fechaBot)->format('Y-m-d')
                        : null;
                } catch (\Exception) {}

                return [
                    'id'                    => $r->id,
                    'tipo'                  => $tipo,
                    'nombre'                => $datos['nombre_responsable'] ?? $r->cliente?->nombre_cliente ?? 'Sin nombre',
                    'telefono'              => $r->cliente?->numero_contacto ?? '',
                    'fecha'                 => $fechaNorm,
                    'hora'                  => $hora,
                    'numero_personas'       => $personas,
                    'sector'                => $datos['sector'] ?? null,
                    'mail'                  => $datos['mail_contacto'] ?? null,
                    'comentarios'           => $datos['extras_texto'] ?? null,
                    'estado'                => $r->estado_reserva,
                    'nombre_hijo'           => $datos['nombre_hijo'] ?? null,
                    'necesidades_especiales'=> $datos['necesidades_especiales'] ?? null,
                ];
            });

        return Inertia::render('reservas', [
            'reservas'     => $reservas,
            'fecha'        => $fecha,
            'ahora'        => Carbon::now()->format('H:i'),
            'es_hoy'       => $fecha === Carbon::today()->format('Y-m-d'),
            'vista'        => $vista,
            'fechas_rango' => $fechasRango->map(fn ($f) => $f->format('Y-m-d'))->values(),
        ]);
    }

    public function update(Request $request, Reserva $reserva): RedirectResponse
    {
        \Log::info('ReservasController@update', [
            'reserva_id'    => $reserva->id,
            'rama_servicio' => $reserva->rama_servicio,
            'subtipo'       => $reserva->subtipo,
            'input'         => $request->only(['nombre','fecha','hora','numero_personas','mail','estado']),
        ]);

        $v = $request->validate([
            'nombre'          => 'required|string|max:255',
            'fecha'           => 'required|date_format:Y-m-d',
            'hora'            => 'nullable|string|max:10',
            'numero_personas' => 'nullable|string|max:100',
            'sector'          => 'nullable|in:Salón,Galería,Terraza,Sin preferencia',
            'mail'            => 'nullable|email|max:255',
            'comentarios'     => 'nullable|string|max:2000',
            'estado'          => 'required|in:CONFIRMADA,PENDIENTE_CONFIRMACION,CANCELADA,ESCALADA,COMPLETADA',
        ]);

        $datos = $reserva->datos ?? [];
        $datos['nombre_responsable'] = $v['nombre'];
        $datos['fecha']              = Carbon::createFromFormat('Y-m-d', $v['fecha'])->format('d/m/y');
        $datos['sector']             = $v['sector'] ?: null;
        $datos['mail_contacto']      = $v['mail'] ?: null;
        $datos['extras_texto']       = $v['comentarios'] ?: null;
        $datos['tiene_extras']       = !empty($v['comentarios']);

        $horaNorm = null;
        if (!empty($v['hora'])) {
            $h = str_replace('.', ':', trim($v['hora']));
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $h, $hm)) {
                $h = sprintf('%02d:%02d', (int) $hm[1], (int) $hm[2]);
            }
            $horaNorm = $h;
        }

        if ($reserva->rama_servicio === 'RESTAURANTE') {
            if ($horaNorm)                     $datos['hora']             = $horaNorm;
            if (!empty($v['numero_personas'])) $datos['numero_personas']  = $v['numero_personas'];
        } elseif ($reserva->subtipo === 'GENERAL_EVT') {
            if ($horaNorm)                     $datos['hora_inicio']      = $horaNorm;
            if (!empty($v['numero_personas'])) $datos['numero_personas']  = (int) $v['numero_personas'];
        } elseif (in_array($reserva->subtipo, ['NINOS', 'FUTBOL', 'PADEL', 'HOCKEY'], true)) {
            if ($horaNorm)                     $datos['hora_inicio']      = $horaNorm;
        }

        $reserva->update([
            'datos'          => $datos,
            'estado_reserva' => $v['estado'],
            'tiene_extras'   => !empty($v['comentarios']),
        ]);

        return redirect()->back();
    }

    public function confirmAllToday(Request $request): RedirectResponse
    {
        $v = $request->validate(['fecha' => 'required|date_format:Y-m-d']);
        $fechaBot = Carbon::createFromFormat('Y-m-d', $v['fecha'])->format('d/m/y');

        Reserva::where('estado_reserva', 'PENDIENTE_CONFIRMACION')
            ->whereRaw("json_extract(datos, '$.fecha') = ?", [$fechaBot])
            ->update(['estado_reserva' => 'CONFIRMADA']);

        return redirect()->back();
    }
}
