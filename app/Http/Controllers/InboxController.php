<?php

namespace App\Http\Controllers;

use App\Models\BotSession;
use App\Models\Cliente;
use App\Models\ConversationMessage;
use App\Services\BotEngine;
use App\Services\Meta\WhatsAppSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * Lista de conversaciones con su estado y último mensaje.
     */
    public function index(Request $request): Response
    {
        $selected = $request->query('numero');

        return Inertia::render('inbox', [
            'conversations' => $this->loadConversations(),
            'selected'      => $selected ? $this->loadConversation($selected) : null,
        ]);
    }

    /**
     * Vista detalle de una conversación específica.
     */
    public function show(string $numero): Response
    {
        return Inertia::render('inbox', [
            'conversations' => $this->loadConversations(),
            'selected'      => $this->loadConversation($numero),
        ]);
    }

    /**
     * Pausa el bot para esta conversación → sender = advisor a partir de ahora.
     */
    public function pause(string $numero): RedirectResponse
    {
        $session = $this->findSessionOrFail($numero);

        if ($session->estado_actual !== 'PAUSADO') {
            $session->mergeEstado([
                'estado_previo_pausa' => $session->estado_actual,
                'estado_actual'       => 'PAUSADO',
                'motivo_pausa'        => 'ASESOR_TAKEOVER',
                'timestamp_pausa'     => now(),
                'next_resume_check_at'=> now()->addHour(),  // §6.1: caja de confirmación a la 1h
            ]);
        } else {
            // Ya estaba pausado por otro motivo (ej. SOLICITUD_CLIENTE) — el asesor lo toma.
            $session->mergeEstado([
                'motivo_pausa'        => 'ASESOR_TAKEOVER',
                'next_resume_check_at'=> now()->addHour(),
            ]);
        }

        return back();
    }

    /**
     * Reanuda el bot al estado_previo_pausa (botón "Solucionado" del §6.1.A).
     */
    public function resume(string $numero): RedirectResponse
    {
        $session = $this->findSessionOrFail($numero);

        $session->mergeEstado([
            'estado_actual'          => $session->estado_previo_pausa ?? 'INICIO',
            'estado_previo_pausa'    => null,
            'motivo_pausa'           => null,
            'timestamp_pausa'        => null,
            'next_resume_check_at'   => null,
            'resolved_by_advisor_at' => now(),
            'unread_count'           => 0,
        ]);

        return back();
    }

    /**
     * "Todavía no" en la caja de confirmación: reagenda la próxima pregunta a la 1h.
     * (§6.1.B)
     */
    public function snooze(string $numero): RedirectResponse
    {
        $session = $this->findSessionOrFail($numero);

        $session->mergeEstado([
            'next_resume_check_at' => now()->addHour(),
        ]);

        return back();
    }

    /**
     * Reinicia la conversación (vuelve a INICIO + descarta datos parciales).
     */
    public function restart(string $numero): RedirectResponse
    {
        $session = $this->findSessionOrFail($numero);

        $session->mergeEstado([
            'estado_actual'          => 'INICIO',
            'estado_previo_pausa'    => null,
            'rama_activa'            => null,
            'subtipo_activo'         => null,
            'current_step'           => null,
            'motivo_pausa'           => null,
            'timestamp_pausa'        => null,
            'next_resume_check_at'   => null,
            'resolved_by_advisor_at' => null,
            'datos_parciales'        => [],
            'contador_invalidos'     => 0,
            'unread_count'           => 0,
        ]);

        return back();
    }

    /**
     * El asesor manda un mensaje manual al usuario por WhatsApp.
     */
    public function reply(Request $request, string $numero, WhatsAppSender $sender): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:4000']);

        $session = $this->findSessionOrFail($numero);

        $msg = $sender->sendAdvisorMessage($session, $request->input('message'));

        return response()->json([
            'message' => [
                'id'        => $msg->id,
                'sender'    => $msg->sender,
                'body'      => $msg->body,
                'created_at'=> $msg->created_at?->toIso8601String(),
                'wa_status' => $msg->wa_status,
            ],
        ]);
    }

    /**
     * El asesor edita el nombre del cliente desde el panel.
     */
    public function updateCliente(Request $request, string $numero): RedirectResponse
    {
        $request->validate(['nombre' => 'required|string|max:255']);

        $session = $this->findSessionOrFail($numero);
        $cliente = Cliente::find($session->id_cliente);
        $cliente?->update(['nombre_cliente' => trim($request->input('nombre'))]);

        return back();
    }

    /**
     * Marca como leído (resetea unread_count). Lo llama el frontend al abrir la conversación.
     */
    public function markRead(string $numero): JsonResponse
    {
        $session = BotSession::where('numero_contacto', $numero)->first();
        if ($session && $session->unread_count > 0) {
            $session->mergeEstado(['unread_count' => 0]);
        }
        return response()->json(['ok' => true]);
    }

    /**
     * Polling liviano de la lista de conversaciones (cada 3s desde el frontend).
     * Devuelve la misma forma que `loadConversations()` para que el frontend pueda
     * reemplazar el array completo.
     */
    public function poll(): JsonResponse
    {
        return response()->json([
            'conversations'    => $this->loadConversations(),
            'inboxUnreadTotal' => (int) BotSession::where('estado_actual', 'PAUSADO')->sum('unread_count'),
            'now'              => now()->toIso8601String(),
        ]);
    }

    /**
     * Polling del chat seleccionado (cada 2s). Devuelve mensajes con id > $after y el estado
     * actualizado de la sesión (para que la modal de §6.1 reaccione si el server actualizó
     * `next_resume_check_at` o si el sweeper reseteó la sesión).
     */
    public function pollChat(Request $request, string $numero): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $session = BotSession::where('numero_contacto', $numero)->first();
        if (!$session) {
            return response()->json(['exists' => false]);
        }

        $newMessages = $session->messages()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get(['id','direction','sender','body','wa_status','created_at'])
            ->map(fn (ConversationMessage $m) => [
                'id'         => $m->id,
                'direction'  => $m->direction,
                'sender'     => $m->sender,
                'body'       => $m->body,
                'wa_status'  => $m->wa_status,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();

        return response()->json([
            'exists'      => true,
            'newMessages' => $newMessages,
            'session' => [
                'estado_actual'        => $session->estado_actual,
                'rama_activa'          => $session->rama_activa,
                'subtipo_activo'       => $session->subtipo_activo,
                'current_step'         => $session->current_step,
                'motivo_pausa'         => $session->motivo_pausa,
                'estado_previo_pausa'  => $session->estado_previo_pausa,
                'datos_parciales'      => $session->datos_parciales ?? [],
                'timestamp_pausa'      => $session->timestamp_pausa?->toIso8601String(),
                'next_resume_check_at' => $session->next_resume_check_at?->toIso8601String(),
                'last_message_at'      => $session->last_message_at?->toIso8601String(),
            ],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function findSessionOrFail(string $numero): BotSession
    {
        return BotSession::where('numero_contacto', $numero)->firstOrFail();
    }

    /**
     * Lista resumida de conversaciones, ordenada por última actividad.
     */
    private function loadConversations(): array
    {
        return BotSession::query()
            ->with('cliente:id,nombre_cliente,numero_contacto')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (BotSession $s) => [
                'numero'           => $s->numero_contacto,
                'nombre'           => $s->cliente?->nombre_cliente,
                'estado_actual'    => $s->estado_actual,
                'motivo_pausa'     => $s->motivo_pausa,
                'last_message_at'  => $s->last_message_at?->toIso8601String(),
                'unread_count'     => (int) $s->unread_count,
                'last_message'     => $this->lastMessagePreview($s->id),
            ])
            ->values()
            ->all();
    }

    /**
     * Detalle completo de una conversación con todos sus mensajes.
     */
    private function loadConversation(string $numero): ?array
    {
        $session = BotSession::where('numero_contacto', $numero)
            ->with('cliente')
            ->first();

        if (!$session) return null;

        // Marca como leído al abrir
        if ($session->unread_count > 0) {
            $session->mergeEstado(['unread_count' => 0]);
        }

        $messages = $session->messages()
            ->orderBy('id')
            ->get(['id','direction','sender','body','wa_status','created_at']);

        $cliente = $session->cliente;

        return [
            'numero'              => $session->numero_contacto,
            'nombre'              => $cliente?->nombre_cliente,
            'cliente'             => $cliente ? [
                'id'                            => $cliente->id,
                'nombre_cliente'                => $cliente->nombre_cliente,
                'mail'                          => $cliente->mail_contacto,
                'contador_reservas_deportes'    => (int) ($cliente->contador_reservas_deportes ?? 0),
                'contador_reservas_restaurante' => (int) ($cliente->contador_reservas_restaurante ?? 0),
                'contador_reservas_eventos'     => (int) ($cliente->contador_reservas_eventos ?? 0),
            ] : null,
            'session' => [
                'estado_actual'           => $session->estado_actual,
                'rama_activa'             => $session->rama_activa,
                'subtipo_activo'          => $session->subtipo_activo,
                'current_step'            => $session->current_step,
                'motivo_pausa'            => $session->motivo_pausa,
                'estado_previo_pausa'     => $session->estado_previo_pausa,
                'datos_parciales'         => $session->datos_parciales ?? [],
                'timestamp_pausa'         => $session->timestamp_pausa?->toIso8601String(),
                'next_resume_check_at'    => $session->next_resume_check_at?->toIso8601String(),
                'last_message_at'         => $session->last_message_at?->toIso8601String(),
            ],
            'messages' => $messages->map(fn (ConversationMessage $m) => [
                'id'        => $m->id,
                'direction' => $m->direction,
                'sender'    => $m->sender,
                'body'      => $m->body,
                'wa_status' => $m->wa_status,
                'created_at'=> $m->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function lastMessagePreview(int $sessionId): ?array
    {
        $msg = ConversationMessage::where('bot_session_id', $sessionId)
            ->orderByDesc('id')
            ->first(['sender','body','created_at']);

        if (!$msg) return null;

        return [
            'sender'    => $msg->sender,
            'body'      => mb_substr($msg->body, 0, 80),
            'created_at'=> $msg->created_at?->toIso8601String(),
        ];
    }
}
