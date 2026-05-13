<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppClient
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
        private readonly string $apiVersion,
        private readonly string $appSecret,
    ) {}

    /**
     * Manda un mensaje de texto plano al número indicado.
     * Devuelve el wa_message_id si Meta lo aceptó, o lanza `MetaApiException` con el `kind` adecuado.
     *
     * @param string $to Número en formato E.164 sin '+' (ej. "5491100000001"), solo dígitos.
     * @throws MetaApiException
     */
    public function sendText(string $to, string $body): string
    {
        $response = $this->request('POST', "{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $body],
        ]);

        $waId = data_get($response, 'messages.0.id');

        if (!$waId) {
            throw new MetaApiException(
                'Meta no devolvió wa_message_id: ' . json_encode($response),
                MetaApiException::KIND_UNKNOWN,
                rawResponse: $response,
            );
        }

        return $waId;
    }

    /**
     * Manda un mensaje con imagen al número indicado.
     * La imagen debe ser una URL pública accesible por Meta.
     *
     * @throws MetaApiException
     */
    public function sendImage(string $to, string $imageUrl, string $caption = ''): string
    {
        $image = ['link' => $imageUrl];
        if ($caption !== '') {
            $image['caption'] = $caption;
        }

        $response = $this->request('POST', "{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'image',
            'image'             => $image,
        ]);

        $waId = data_get($response, 'messages.0.id');

        if (!$waId) {
            throw new MetaApiException(
                'Meta no devolvió wa_message_id: ' . json_encode($response),
                MetaApiException::KIND_UNKNOWN,
                rawResponse: $response,
            );
        }

        return $waId;
    }

    /**
     * Verifica la firma X-Hub-Signature-256 del webhook contra el app secret.
     * Meta firma el body crudo con HMAC-SHA256.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (!$signatureHeader || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->appSecret);

        return hash_equals($expected, $signatureHeader);
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$path}";

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->send($method, $url, ['json' => $payload]);

        if ($response->failed()) {
            $body     = $response->json() ?? [];
            $metaCode = (int) (data_get($body, 'error.code') ?? 0);
            $kind     = $this->classifyError($response->status(), $metaCode);

            Log::error('WhatsApp Cloud API call failed', [
                'method'    => $method,
                'path'      => $path,
                'status'    => $response->status(),
                'meta_code' => $metaCode,
                'kind'      => $kind,
                'body'      => $response->body(),
            ]);

            throw new MetaApiException(
                "WhatsApp API {$method} {$path} → HTTP {$response->status()} (meta_code={$metaCode}, kind={$kind})",
                kind:        $kind,
                httpStatus:  $response->status(),
                metaCode:    $metaCode,
                rawResponse: $body,
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Mapea el HTTP status + Meta error code a un kind operacional.
     * Documentación: https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes
     */
    private function classifyError(int $httpStatus, int $metaCode): string
    {
        // HTTP-level
        if ($httpStatus === 401 || $httpStatus === 403) {
            return MetaApiException::KIND_AUTH;
        }
        if ($httpStatus === 429) {
            return MetaApiException::KIND_RATE_LIMIT;
        }
        if ($httpStatus >= 500) {
            return MetaApiException::KIND_SERVER;
        }

        // Meta-specific
        return match ($metaCode) {
            190               => MetaApiException::KIND_AUTH,        // OAuth token expired/invalid
            131005, 131008    => MetaApiException::KIND_AUTH,        // access denied / required param missing (suele ser auth-related)
            131026, 131021    => MetaApiException::KIND_BLOCKED,     // recipient unreachable / no es WhatsApp user
            131048, 130429    => MetaApiException::KIND_RATE_LIMIT,  // spam rate limit / api rate limit
            131016            => MetaApiException::KIND_SERVER,      // service unavailable
            default           => MetaApiException::KIND_UNKNOWN,
        };
    }
}
