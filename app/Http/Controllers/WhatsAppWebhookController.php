<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\WhatsAppChannel;
use App\Services\Meta\WhatsAppClient;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handshake de verificación que Meta hace una vez al configurar el webhook.
     * https://developers.facebook.com/docs/graph-api/webhooks/getting-started
     */
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('services.whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $expectedToken && $challenge !== null) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('@WhatsAppWebhookController-verify: verificación fallida', [
            'mode'        => $mode,
            'token_match' => $token === $expectedToken,
            'challenge'   => $challenge,
        ]);

        return response('forbidden', 403);
    }

    /**
     * Recibe mensajes entrantes de WhatsApp. Meta espera 200 en <5s, así que
     * hacemos lo mínimo en sincronía (validar firma + parsear) y delegamos
     * el procesamiento real a un job en cola.
     * IMPORTANTE: siempre devolvemos 200 en errores internos para que Meta no reintente.
     */
    public function receive(Request $request, WhatsAppClient $client): Response
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (!$client->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('@WhatsAppWebhookController-receive: firma inválida', [
                'signature' => $signature,
                'ip'        => $request->ip(),
            ]);
            return response('invalid signature', 403);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('ignored', 200);
        }

        try {
            $channelMap = WhatsAppChannel::where('is_active', true)->pluck('id', 'phone_number_id');
        } catch (\Throwable $e) {
            Log::error('@WhatsAppWebhookController-receive: error cargando canales desde BD', [
                'error' => $e->getMessage(),
            ]);
            return response('ok', 200);
        }

        foreach ($this->extractMessages($payload, $channelMap) as $msg) {
            try {
                ProcessIncomingWhatsAppMessage::dispatch(
                    from:        $msg['from'],
                    body:        $msg['body'],
                    waMessageId: $msg['id'],
                    messageType: $msg['type'] ?? 'text',
                    channelId:   $msg['channel_id'],
                );
            } catch (\Throwable $e) {
                Log::error('@WhatsAppWebhookController-receive: error despachando job', [
                    'from'  => $msg['from'],
                    'wa_id' => $msg['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response('ok', 200);
    }

    /**
     * Aplana el payload y devuelve mensajes que entendemos (text / button / interactive),
     * más un placeholder legible para media (audio/imagen/video/etc.) — antes esos tipos
     * llegaban con body vacío ("" ) y se veían como una burbuja en blanco en el inbox.
     * Status updates se ignoran por ahora.
     * Si channelMap está vacío (sin canales en BD), hace fallback al phone_number_id global.
     *
     * @param  \Illuminate\Support\Collection<string,int>  $channelMap  phone_number_id => channel_id
     * @return array<int, array{from: string, body: string, id: string, type: string, channel_id: int|null}>
     */
    private function extractMessages(array $payload, \Illuminate\Support\Collection $channelMap): array
    {
        $out = [];

        $fallbackPhoneNumberId = $channelMap->isEmpty()
            ? config('services.whatsapp.phone_number_id')
            : null;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }
                $value           = $change['value'] ?? [];
                $incomingPhoneId = $value['metadata']['phone_number_id'] ?? null;

                if ($channelMap->isNotEmpty()) {
                    if (!$channelMap->has($incomingPhoneId)) {
                        continue;
                    }
                    $channelId = $channelMap->get($incomingPhoneId);
                } else {
                    if ($fallbackPhoneNumberId && $incomingPhoneId !== $fallbackPhoneNumberId) {
                        continue;
                    }
                    $channelId = null;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    $type = $message['type'] ?? null;
                    $body = match ($type) {
                        'text'        => $message['text']['body'] ?? '',
                        'button'      => $message['button']['text'] ?? '',
                        'interactive' => $message['interactive']['button_reply']['title']
                                       ?? $message['interactive']['list_reply']['title']
                                       ?? '',
                        'image'       => '[Imagen]',
                        'audio'       => '[Audio]',
                        'video'       => '[Video]',
                        'document'    => '[Documento]',
                        'sticker'     => '[Sticker]',
                        'location'    => '[Ubicación]',
                        'contacts'    => '[Contacto]',
                        'reaction'    => '[Reacción] ' . ($message['reaction']['emoji'] ?? ''),
                        default       => $type !== null ? '[Mensaje no compatible]' : '',
                    };
                    $from = PhoneNumber::normalize($message['from'] ?? null);
                    $id   = $message['id'] ?? null;

                    if ($from === '' || $id === null || $type === null) {
                        continue;
                    }

                    $isNonText = !in_array($type, ['text', 'button', 'interactive'], true);
                    if (!$isNonText && $body === '') {
                        continue;
                    }

                    $out[] = ['from' => $from, 'body' => $body, 'id' => $id, 'type' => $type, 'channel_id' => $channelId];
                }
            }
        }

        return $out;
    }
}
