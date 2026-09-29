<?php

namespace App\Http\Controllers;

use App\Services\Meta\WhatsAppClient;
use App\Services\WhatsAppFlows\Exceptions\FlowDecryptionException;
use App\Services\WhatsAppFlows\Exceptions\InvalidFlowTokenException;
use App\Services\WhatsAppFlows\FlowCrypto;
use App\Services\WhatsAppFlows\ReservaFlowService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint (data_exchange) del WhatsApp Flow de reserva de restaurante.
 * Códigos que espera Meta: 432 firma inválida, 421 no se pudo descifrar, 427 flow_token inválido.
 */
class WhatsAppFlowController extends Controller
{
    public function reserva(Request $request, WhatsAppClient $client, ReservaFlowService $service): Response
    {
        if (!$client->verifyWebhookSignature($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            Log::warning('[FLOW][ENDPOINT] Firma inválida', ['ip' => $request->ip()]);
            return response('', 432);
        }

        $crypto = FlowCrypto::fromConfig();

        try {
            $decrypted = $crypto->decryptRequest($request->json()->all());
        } catch (FlowDecryptionException $e) {
            Log::warning('[FLOW][ENDPOINT] No se pudo descifrar', ['error' => $e->getMessage()]);
            return response('', 421);
        }

        try {
            $out = $service->handle($decrypted->payload);
        } catch (InvalidFlowTokenException $e) {
            Log::info('[FLOW][ENDPOINT] flow_token inválido', ['action' => $decrypted->action()]);
            return response('', 427);
        }

        return response($crypto->encryptResponse($out, $decrypted->aesKey, $decrypted->iv), 200)
            ->header('Content-Type', 'text/plain');
    }
}
