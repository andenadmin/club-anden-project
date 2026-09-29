<?php

namespace Tests\Unit\WhatsAppFlows;

use App\Services\WhatsAppFlows\Exceptions\FlowDecryptionException;
use App\Services\WhatsAppFlows\FlowCrypto;
use phpseclib3\Crypt\RSA;
use PHPUnit\Framework\TestCase;

class FlowCryptoTest extends TestCase
{
    private static RSA\PrivateKey $key;

    public static function setUpBeforeClass(): void
    {
        self::$key = RSA::createKey(2048);
    }

    /** Arma el body tal como lo manda Meta: payload AES-GCM + clave AES cifrada con la pública (OAEP SHA-256). */
    private function metaRequest(array $payload, ?string &$aesKey = null, ?string &$iv = null): array
    {
        $aesKey = random_bytes(16);
        $iv     = random_bytes(16);
        $tag    = '';
        $ct     = openssl_encrypt(json_encode($payload), 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        $encKey = self::$key->getPublicKey()
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256')
            ->encrypt($aesKey);

        return [
            'encrypted_flow_data' => base64_encode($ct . $tag),
            'encrypted_aes_key'   => base64_encode($encKey),
            'initial_vector'      => base64_encode($iv),
        ];
    }

    private function crypto(): FlowCrypto
    {
        return new FlowCrypto(self::$key->toString('PKCS8'));
    }

    public function test_descifra_el_request_de_meta(): void
    {
        $payload = ['version' => '3.0', 'action' => 'INIT', 'flow_token' => 'abc', 'data' => ['ñandú' => 'sí']];

        $req = $this->crypto()->decryptRequest($this->metaRequest($payload, $aesKey, $iv));

        $this->assertSame($payload, $req->payload);
        $this->assertSame('INIT', $req->action());
        $this->assertSame($aesKey, $req->aesKey);
        $this->assertSame($iv, $req->iv);
    }

    public function test_cifra_la_respuesta_con_el_iv_invertido(): void
    {
        $req      = $this->crypto()->decryptRequest($this->metaRequest(['action' => 'ping'], $aesKey, $iv));
        $response = ['data' => ['status' => 'active']];

        $encoded = $this->crypto()->encryptResponse($response, $req->aesKey, $req->iv);

        $raw     = base64_decode($encoded, true);
        $flipped = '';
        for ($i = 0; $i < strlen($iv); $i++) {
            $flipped .= chr(ord($iv[$i]) ^ 0xFF);
        }
        $plain = openssl_decrypt(substr($raw, 0, -16), 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $flipped, substr($raw, -16));

        $this->assertSame($response, json_decode($plain, true));
        $this->assertFalse(
            openssl_decrypt(substr($raw, 0, -16), 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, substr($raw, -16)),
            'La respuesta no debe poder descifrarse con el IV original.'
        );
    }

    public function test_rechaza_payload_alterado(): void
    {
        $body = $this->metaRequest(['action' => 'INIT']);
        $raw  = base64_decode($body['encrypted_flow_data']);
        $raw[0] = chr(ord($raw[0]) ^ 0x01);
        $body['encrypted_flow_data'] = base64_encode($raw);

        $this->expectException(FlowDecryptionException::class);
        $this->crypto()->decryptRequest($body);
    }

    public function test_rechaza_clave_aes_cifrada_con_otra_clave_publica(): void
    {
        $body  = $this->metaRequest(['action' => 'INIT']);
        $otra  = RSA::createKey(2048);
        $body['encrypted_aes_key'] = base64_encode(
            $otra->getPublicKey()->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256')->encrypt(random_bytes(16))
        );

        $this->expectException(FlowDecryptionException::class);
        $this->crypto()->decryptRequest($body);
    }

    public function test_rechaza_campos_faltantes_o_no_base64(): void
    {
        $body = $this->metaRequest(['action' => 'INIT']);
        $body['initial_vector'] = '%%%no-base64%%%';

        $this->expectException(FlowDecryptionException::class);
        $this->crypto()->decryptRequest($body);
    }

    public function test_acepta_clave_privada_con_passphrase(): void
    {
        $pem    = self::$key->withPassword('secreto')->toString('PKCS8');
        $crypto = new FlowCrypto($pem, 'secreto');

        $req = $crypto->decryptRequest($this->metaRequest(['action' => 'ping']));

        $this->assertSame('ping', $req->action());
    }

    public function test_normaliza_pem_escapado_y_base64(): void
    {
        $pem = self::$key->toString('PKCS8');

        $this->assertSame($pem, FlowCrypto::normalizePem(str_replace("\n", '\n', $pem)));
        $this->assertSame($pem, FlowCrypto::normalizePem(base64_encode($pem)));
    }
}
