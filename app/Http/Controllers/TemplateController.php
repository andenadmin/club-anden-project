<?php

namespace App\Http\Controllers;

use App\Models\BotSession;
use App\Services\Meta\WhatsAppSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * Lista las plantillas disponibles definidas en config/wa_templates.php.
     */
    public function index(): JsonResponse
    {
        return response()->json(config('wa_templates', []));
    }

    /**
     * Envía una plantilla al número de una conversación.
     */
    public function send(Request $request, string $numero, WhatsAppSender $sender): JsonResponse
    {
        $v = $request->validate([
            'template_id' => 'required|string',
            'variables'   => 'present|array',
            'variables.*' => 'nullable|string|max:500',
        ]);

        $templates = collect(config('wa_templates', []));
        $template  = $templates->firstWhere('id', $v['template_id']);

        if (!$template) {
            return response()->json(['error' => 'Plantilla no encontrada'], 404);
        }

        $session = BotSession::where('numero_contacto', $numero)->firstOrFail();

        $msg = $sender->sendTemplate(
            $session,
            $template['name'],
            $template['language'],
            array_values($v['variables']),
        );

        return response()->json([
            'message' => [
                'id'         => $msg->id,
                'sender'     => $msg->sender,
                'body'       => $msg->body,
                'wa_status'  => $msg->wa_status,
                'created_at' => $msg->created_at?->toIso8601String(),
            ],
        ]);
    }
}
