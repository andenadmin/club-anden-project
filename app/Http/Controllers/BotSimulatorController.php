<?php

namespace App\Http\Controllers;

use App\Models\BotSession;
use App\Models\ConversationMessage;
use App\Services\BotEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class BotSimulatorController extends Controller
{
    private const SIMULATOR_FROM = '5491100000001';

    public function index(): Response
    {
        $session = BotSession::where('numero_contacto', self::SIMULATOR_FROM)->first();

        $history = $session
            ? $session->messages()
                ->orderBy('id')
                ->get(['direction', 'sender', 'body', 'created_at'])
                ->map(fn (ConversationMessage $m) => [
                    'role' => $m->sender === ConversationMessage::SENDER_USER ? 'user' : 'bot',
                    'text' => $m->body,
                    'ts'   => $m->created_at?->toIso8601String(),
                ])
                ->all()
            : [];

        return Inertia::render('bot-simulator', [
            'history' => $history,
            'sessionState' => $session ? [
                'estado_actual'  => $session->estado_actual,
                'rama_activa'    => $session->rama_activa,
                'subtipo_activo' => $session->subtipo_activo,
                'current_step'   => $session->current_step,
            ] : null,
        ]);
    }

    public function message(Request $request, BotEngine $engine): JsonResponse
    {
        $request->validate([
            'numero_contacto' => 'required|string',
            'message'         => 'required|string|max:1000',
        ]);

        $from = $request->input('numero_contacto');
        $text = $request->input('message');

        try {
            $messages = $engine->process($from, $text);
        } catch (\Throwable $e) {
            Log::error('@BotSimulatorController-message: error en BotEngine::process', [
                'from'  => $from,
                'text'  => $text,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error interno al procesar el mensaje. Revisá los logs.'], 500);
        }

        $session = BotSession::where('numero_contacto', $from)->first();

        if ($session) {
            foreach ($messages as $body) {
                $engine->logOutbound($session, $body);
            }
        }

        return response()->json([
            'messages'      => $messages,
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

        $from    = $request->input('numero_contacto');
        $session = BotSession::where('numero_contacto', $from)->first();

        if ($session) {
            $session->messages()->delete();
            $session->delete();
        }

        return response()->json(['success' => true]);
    }
}
