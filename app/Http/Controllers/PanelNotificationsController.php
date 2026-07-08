<?php

namespace App\Http\Controllers;

use App\Models\PanelNotification;
use App\Models\RestaurantConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PanelNotificationsController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = PanelNotification::where('leida', false)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($notifications);
    }

    public function markRead(PanelNotification $notification): JsonResponse
    {
        $notification->update(['leida' => true]);

        return response()->json(['ok' => true]);
    }

    public function action(Request $request, PanelNotification $notification): JsonResponse
    {
        $accion = $request->input('accion');

        if ($notification->tipo === 'sector_alerta' && $accion === 'informar') {
            $sector = $notification->payload['sector_key'] ?? null;

            if (!$sector) {
                Log::warning('@PanelNotificationsController-action: sector_key ausente en payload', [
                    'notification_id' => $notification->id,
                    'payload'         => $notification->payload,
                ]);
            } elseif (!in_array($sector, ['salon', 'galeria', 'terraza', 'parrilla', 'patio'], true)) {
                Log::warning('@PanelNotificationsController-action: sector_key inválido', [
                    'notification_id' => $notification->id,
                    'sector'          => $sector,
                ]);
            } else {
                $config = RestaurantConfig::first();

                if (!$config) {
                    Log::error('@PanelNotificationsController-action: RestaurantConfig no existe en BD');
                } else {
                    try {
                        $config->update([
                            "{$sector}_cerrado"      => true,
                            'sectores_cerrado_fecha' => now()->toDateString(),
                        ]);
                        RestaurantConfig::clearCache();
                    } catch (\Throwable $e) {
                        Log::error('@PanelNotificationsController-action: error al cerrar sector', [
                            'sector' => $sector,
                            'error'  => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $notification->update(['leida' => true]);

        return response()->json(['ok' => true]);
    }
}
