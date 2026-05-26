<?php

namespace App\Http\Controllers;

use App\Models\PanelNotification;
use App\Models\RestaurantConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelNotificationsController extends Controller
{
    /**
     * Retorna todas las notificaciones no leídas, ordenadas por más reciente.
     */
    public function index(): JsonResponse
    {
        $notifications = PanelNotification::where('leida', false)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($notifications);
    }

    /**
     * Marca una notificación como leída.
     */
    public function markRead(PanelNotification $notification): JsonResponse
    {
        $notification->update(['leida' => true]);

        return response()->json(['ok' => true]);
    }

    /**
     * Ejecuta una acción sobre la notificación según su tipo.
     * Para tipo `sector_alerta`:
     *   - accion = 'informar' → cierra el sector en RestaurantConfig y marca como leída.
     *   - accion = 'mantener' → solo marca como leída.
     */
    public function action(Request $request, PanelNotification $notification): JsonResponse
    {
        $accion = $request->input('accion');

        if ($notification->tipo === 'sector_alerta' && $accion === 'informar') {
            $sector = $notification->payload['sector_key'] ?? null;

            if ($sector && in_array($sector, ['salon', 'galeria', 'terraza', 'parrilla'], true)) {
                $config = RestaurantConfig::first();
                if ($config) {
                    $config->update([
                        "{$sector}_cerrado"      => true,
                        'sectores_cerrado_fecha' => now()->toDateString(),
                    ]);
                    RestaurantConfig::clearCache();
                }
            }
        }

        $notification->update(['leida' => true]);

        return response()->json(['ok' => true]);
    }
}
