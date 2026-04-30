<?php

namespace App\Http\Controllers;

use App\Models\BotSession;
use App\Services\BotEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotSimulatorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('bot-simulator');
    }

    public function message(Request $request, BotEngine $engine): JsonResponse
    {
        $request->validate([
            'numero_contacto' => 'required|string',
            'message'         => 'required|string|max:1000',
        ]);

        $from     = $request->input('numero_contacto');
        $text     = $request->input('message');
        $messages = $engine->process($from, $text);

        $session = BotSession::where('numero_contacto', $from)->first();

        return response()->json([
            'messages'     => $messages,
            'session_state' => $session ? [
                'estado_actual'  => $session->estado_actual,
                'rama_activa'    => $session->rama_activa,
                'subtipo_activo' => $session->subtipo_activo,
                'current_step'   => $session->current_step,
            ] : null,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate(['numero_contacto' => 'required|string']);
        BotSession::where('numero_contacto', $request->input('numero_contacto'))->delete();
        return response()->json(['success' => true]);
    }
}
