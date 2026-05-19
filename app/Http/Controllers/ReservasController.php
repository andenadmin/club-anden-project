<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservasController extends Controller
{
    public function index(Request $request): Response
    {
        $fecha    = $request->input('fecha', Carbon::today()->format('Y-m-d'));
        $fechaBot = Carbon::createFromFormat('Y-m-d', $fecha)->format('d/m/y');

        $reservas = Reserva::with('cliente')
            ->where('rama_servicio', 'RESTAURANTE')
            ->whereRaw("json_extract(datos, '$.fecha') = ?", [$fechaBot])
            ->whereNotIn('estado_reserva', ['CANCELADA'])
            ->orderByRaw("json_extract(datos, '$.hora')")
            ->get()
            ->map(function (Reserva $r) {
                $datos = $r->datos ?? [];
                return [
                    'id'              => $r->id,
                    'nombre'          => $datos['nombre_responsable'] ?? $r->cliente?->nombre_cliente ?? 'Sin nombre',
                    'telefono'        => $r->cliente?->numero_contacto ?? '',
                    'hora'            => $datos['hora'] ?? '',
                    'numero_personas' => $datos['numero_personas'] ?? '',
                    'mail'            => $datos['mail_contacto'] ?? null,
                    'comentarios'     => $datos['extras_texto'] ?? null,
                    'estado'          => $r->estado_reserva,
                ];
            });

        return Inertia::render('reservas', [
            'reservas' => $reservas,
            'fecha'    => $fecha,
            'ahora'    => Carbon::now()->format('H:i'),
        ]);
    }
}
