<?php

namespace App\Services\WhatsAppFlows;

use App\Services\WhatsAppFlows\Exceptions\FlowDecryptionException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;

/**
 * Esquema de cifrado de los endpoints de WhatsApp Flows:
 * https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint
 *
 * - Clave AES-128 cifrada con RSA-OAEP (SHA-256, MGF1 SHA-256). openssl_private_decrypt()
 *   de PHP < 8.5 solo soporta OAEP con SHA-1, por eso se usa phpseclib.
 * - Payload en AES-128-GCM con el tag de 16 bytes al final.
 * - La respuesta se cifra con la misma clave y el IV con todos los bits invertidos.
 */
class FlowCrypto
{
    private const TAG_LENGTH = 16;

    public function __construct(
        private readonly string $privateKeyPem,
        private readonly ?string $passphrase = null,
    ) {}

    public static function fromConfig(): self
    {
        $raw = (string) config('whatsapp_flows.private_key');
        if ($raw === '') {
            throw new \RuntimeException('Falta WA_FLOW_PRIVATE_KEY en el entorno.');
        }

        return new self(self::normalizePem($raw), config('whatsapp_flows.private_key_passphrase') ?: null);
    }

    /** Acepta el PEM literal (con "\n" escapados, como suele quedar en un .env) o el PEM en base64. */
    public static function normalizePem(string $raw): string
    {
        $raw = trim($raw);
        if (str_contains($raw, '-----BEGIN')) {
            return str_replace('\n', "\n", $raw);
        }

        $decoded = base64_decode($raw, true);

        return $decoded !== false ? $decoded : $raw;
    }

    /**
     * @param  array{encrypted_flow_data?: string, encrypted_aes_key?: string, initial_vector?: string}  $body
     * @throws FlowDecryptionException
     */
    public function decryptRequest(array $body): DecryptedFlowRequest
    {
        $flowData = $this->b64($body['encrypted_flow_data'] ?? null, 'encrypted_flow_data');
        $aesKeyCt = $this->b64($body['encrypted_aes_key'] ?? null, 'encrypted_aes_key');
        $iv       = $this->b64($body['initial_vector'] ?? null, 'initial_vector');

        try {
            $key = PublicKeyLoader::loadPrivateKey($this->privateKeyPem, $this->passphrase ?? false);
            if (!$key instanceof RSA\PrivateKey) {
                throw new FlowDecryptionException('La clave privada no es RSA.');
            }
            $aesKey = $key->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->decrypt($aesKeyCt);
        } catch (FlowDecryptionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FlowDecryptionException('No se pudo descifrar la clave AES: ' . $e->getMessage(), previous: $e);
        }

        if (!is_string($aesKey) || strlen($aesKey) !== 16) {
            throw new FlowDecryptionException('La clave AES descifrada no es de 128 bits.');
        }

        if (strlen($flowData) <= self::TAG_LENGTH) {
            throw new FlowDecryptionException('encrypted_flow_data demasiado corto.');
        }

        $ciphertext = substr($flowData, 0, -self::TAG_LENGTH);
        $tag        = substr($flowData, -self::TAG_LENGTH);
        $plain      = openssl_decrypt($ciphertext, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plain === false) {
            throw new FlowDecryptionException('Falló el descifrado AES-GCM (tag inválido).');
        }

        try {
            $payload = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new FlowDecryptionException('El payload descifrado no es JSON válido.', previous: $e);
        }

        if (!is_array($payload)) {
            throw new FlowDecryptionException('El payload descifrado no es un objeto.');
        }

        return new DecryptedFlowRequest($payload, $aesKey, $iv);
    }

    public function encryptResponse(array $response, string $aesKey, string $iv): string
    {
        $flippedIv = ~$iv;
        $tag       = '';
        $json      = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $ciphertext = openssl_encrypt($json, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $flippedIv, $tag, '', self::TAG_LENGTH);
        if ($ciphertext === false) {
            throw new \RuntimeException('Falló el cifrado AES-GCM de la respuesta.');
        }

        return base64_encode($ciphertext . $tag);
    }

    private function b64(mixed $value, string $field): string
    {
        if (!is_string($value) || $value === '') {
            throw new FlowDecryptionException("Falta {$field}.");
        }
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new FlowDecryptionException("{$field} no es base64 válido.");
        }

        return $decoded;
    }
}
