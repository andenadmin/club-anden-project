<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingWhatsAppMessage;
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

        return response('forbidden', 403);
    }

    /**
     * Recibe mensajes entrantes de WhatsApp. Meta espera 200 en <5s, así que
     * hacemos lo mínimo en sincronía (validar firma + parsear) y delegamos
     * el procesamiento real a un job en cola.
     */
    public function receive(Request $request, WhatsAppClient $client): Response
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (!$client->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('WhatsApp webhook con firma inválida', ['signature' => $signature]);
            return response('invalid signature', 403);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('ignored', 200);
        }

        $expectedPhoneNumberId = config('services.whatsapp.phone_number_id');

        foreach ($this->extractMessages($payload, $expectedPhoneNumberId) as $msg) {
            ProcessIncomingWhatsAppMessage::dispatch(
                from:        $msg['from'],
                body:        $msg['body'],
                waMessageId: $msg['id'],
            );
        }

        return response('ok', 200);
    }

    /**
     * Aplana el payload y devuelve mensajes que entendemos (text / button / interactive).
     * Status updates, media, audio, etc. se ignoran por ahora.
     *
     * @return array<int, array{from: string, body: string, id: string}>
     */
    private function extractMessages(array $payload, ?string $expectedPhoneNumberId): array
    {
        $out = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }
                $value = $change['value'] ?? [];

                if ($expectedPhoneNumberId
                    && ($value['metadata']['phone_number_id'] ?? null) !== $expectedPhoneNumberId) {
                    continue;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    $body = match ($message['type'] ?? null) {
                        'text'        => $message['text']['body'] ?? '',
                        'button'      => $message['button']['text'] ?? '',
                        'interactive' => $message['interactive']['button_reply']['title']
                                       ?? $message['interactive']['list_reply']['title']
                                       ?? '',
                        default       => '',
                    };
                    $from = PhoneNumber::normalize($message['from'] ?? null);
                    $id   = $message['id'] ?? null;

                    if ($body === '' || $from === '' || $id === null) {
                        continue;
                    }
                    $out[] = ['from' => $from, 'body' => $body, 'id' => $id];
                }
            }
        }

        return $out;
    }
}
