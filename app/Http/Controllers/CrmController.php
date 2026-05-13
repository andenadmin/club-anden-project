<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        $clientes = Cliente::with('crm')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('nombre_cliente', 'like', "%{$search}%")
                          ->orWhere('numero_contacto', 'like', "%{$search}%")
                          ->orWhere('mail_contacto', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('crm', [
            'clientes' => $clientes,
            'search'   => $search,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = $request->input('search', '');

        $clientes = Cliente::with('crm')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('nombre_cliente', 'like', "%{$search}%")
                          ->orWhere('numero_contacto', 'like', "%{$search}%")
                          ->orWhere('mail_contacto', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->get();

        $filename = 'crm-clientes-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($clientes) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Nombre', 'Teléfono', 'Mail',
                'Reservas Deportes', 'Reservas Restaurante', 'Reservas Eventos',
                'Total Reservas', 'Lifetime Value', 'Último Evento',
                'Etiquetas', 'Notas', 'Registrado',
            ]);

            foreach ($clientes as $c) {
                $crm = $c->crm;
                $etiquetas = $crm?->etiquetas ? implode(', ', $crm->etiquetas) : '';
                $total = $c->contador_reservas_deportes
                       + $c->contador_reservas_restaurante
                       + $c->contador_reservas_eventos;

                fputcsv($handle, [
                    $c->nombre_cliente ?? '',
                    $c->numero_contacto,
                    $c->mail_contacto ?? '',
                    $c->contador_reservas_deportes,
                    $c->contador_reservas_restaurante,
                    $c->contador_reservas_eventos,
                    $total,
                    $crm ? number_format((float)$crm->valor_lifetime, 2, ',', '.') : '0,00',
                    $crm?->fecha_ultimo_evento?->format('d/m/Y') ?? '',
                    $etiquetas,
                    $crm?->notas ?? '',
                    $c->created_at->format('d/m/Y'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
