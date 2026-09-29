<?php

namespace App\Services\WhatsAppFlows;

use App\Models\BotMessageOption;
use App\Models\BotSession;
use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\WhatsAppFlowToken;
use App\Services\BotEngine;
use App\Services\BotMessages;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mensajes del chat alrededor del Flow de reserva: el mensaje interactivo que lo abre
 * y la única respuesta que se manda cuando el cliente lo completa.
 */
class ReservaFlowMessenger
{
    /** Prefijo que BotEngine usa para pedirle a WhatsAppSender que mande el mensaje interactivo del Flow. */
    public const PREFIJO = '[FLOW]';

    /** Límite de Meta para el body de un mensaje interactivo. */
    private const MAX_BODY = 1024;

    public static function habilitado(?BotSession $session = null): bool
    {
        return (bool) config('whatsapp_flows.reserva.enabled')
            && config('whatsapp_flows.reserva.flow_id')
            && !$session?->channel?->default_flow;
    }

    /** Texto del mensaje que abre el Flow: saludo + menú (el resto de las opciones se siguen respondiendo por letra). */
    public static function mensajeBienvenida(?Cliente $cliente): string
    {
        if ($cliente?->nombre_cliente) {
            $body = BotMessages::render('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $cliente->nombre_cliente]);
        } else {
            $lineas = [];
            foreach (BotMessageOption::activos('MENU_PRINCIPAL') as $i => $opcion) {
                $lineas[] = '*' . chr(65 + $i) . '.* ' . $opcion->label;
            }
            $body = "¡Hola! Bienvenido a El Andén 🌿\nSoy Andy, el asistente de reservas.\n\n¿En qué puedo ayudarte hoy?\n\n"
                . implode("\n", $lineas) . "\n\n*0.* Hablar con un asesor";
        }

        $body .= "\n\n🍽️ Para reservar mesa tocá *" . config('whatsapp_flows.reserva.cta') . '*.';

        return self::PREFIJO . mb_substr($body, 0, self::MAX_BODY);
    }

    /**
     * Respuesta al nfm_reply. Devuelve los textos a mandar (a lo sumo uno) y es idempotente:
     * si Meta reenvía el webhook, no se manda dos veces.
     *
     * @return string[]
     */
    public function respuestaAlFormulario(BotSession $session, string $responseJson, BotEngine $engine): array
    {
        $params = json_decode($responseJson, true);
        $token  = is_array($params) && isset($params['flow_token'])
            ? WhatsAppFlowToken::where('token', $params['flow_token'])->where('bot_session_id', $session->id)->first()
            : null;

        if (!$token || !$token->completed_at) {
            Log::warning('[FLOW][RESERVA] nfm_reply sin token completado', ['numero' => $session->numero_contacto]);
            return [];
        }

        $marcado = WhatsAppFlowToken::whereKey($token->id)->whereNull('confirmation_sent_at')->update(['confirmation_sent_at' => now()]);
        if ($marcado === 0) {
            return [];
        }

        if ($token->resultado === 'grupo') {
            return [$engine->escalarGrupoDesdeFlow($session)];
        }

        $reserva = $token->reserva;

        return $reserva ? [$this->confirmacion($reserva)] : [];
    }

    private function confirmacion(Reserva $reserva): string
    {
        $d     = $reserva->datos ?? [];
        $fecha = ucfirst(Carbon::createFromFormat('!d/m/y', $d['fecha'])->locale('es')->isoFormat('dddd D/M'));

        $lineas = [
            '*Tu reserva en El Andén*',
            "📅 {$fecha} · 🕐 {$d['hora']} hs",
            "👥 {$d['numero_personas']} · 🪑 {$d['sector']}",
            "👤 A nombre de {$d['nombre_responsable']}",
        ];
        if (!empty($d['comentarios'])) {
            $lineas[] = "📝 {$d['comentarios']}";
        }

        $cierre = BotMessages::render(
            $reserva->estado_reserva === 'PENDIENTE_CONFIRMACION' ? 'MSG_RESERVA_PRECONFIRMADA' : 'MSG_RESERVA_EXITOSA'
        );

        return implode("\n", $lineas) . ($cierre !== '' ? "\n\n{$cierre}" : '');
    }
}
