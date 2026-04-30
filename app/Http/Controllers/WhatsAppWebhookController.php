<?php

namespace App\Http\Controllers;

use App\Services\BotEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, BotEngine $engine)
    {
        $payload = $request->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('OK', 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') continue;

                $value         = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if ($phoneNumberId !== config('services.whatsapp.phone_number_id')) continue;

                foreach ($value['messages'] ?? [] as $message) {
                    $this->processMessage($message, $value, $engine);
                }
            }
        }

        return response('OK', 200);
    }

    private function processMessage(array $message, array $value, BotEngine $engine): void
    {
        $messageId = $message['id'] ?? null;
        $from      = $message['from'] ?? null;
        $type      = $message['type'] ?? null;

        if (!$messageId || !$from) return;

        // Deduplicación
        $cacheKey = "wa_msg_{$messageId}";
        if (Cache::has($cacheKey)) return;
        Cache::put($cacheKey, true, now()->addHours(24));

        $text = match($type) {
            'text'     => $message['text']['body'] ?? '',
            'button'   => $message['button']['text'] ?? '',
            'interactive' => $message['interactive']['button_reply']['title']
                          ?? $message['interactive']['list_reply']['title']
                          ?? '',
            default    => '',
        };

        if ($text === '') return;

        $responses = $engine->process($from, $text);

        foreach ($responses as $response) {
            $this->sendWhatsAppMessage($from, $response);
        }
    }

    private function sendWhatsAppMessage(string $to, string $text): void
    {
        $token         = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (!$token || !$phoneNumberId) {
            Log::warning('WhatsApp: credenciales no configuradas');
            return;
        }

        Http::withToken($token)->post(
            "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages",
            [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $text],
            ]
        );
    }
}
