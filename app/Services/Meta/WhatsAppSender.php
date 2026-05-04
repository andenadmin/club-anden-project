<?php

namespace App\Services\Meta;

use App\Models\BotSession;
use App\Models\ConversationMessage;
use App\Services\BotEngine;
use App\Support\PhoneNumber;
use Throwable;

class WhatsAppSender
{
    public function __construct(
        private readonly WhatsAppClient $client,
        private readonly BotEngine $engine,
    ) {}

    /**
     * Manda cada respuesta del bot al usuario y la persiste en conversation_messages
     * con el wa_message_id devuelto por Meta. Si una falla, las siguientes igual se intentan
     * (queremos best-effort por mensaje, no all-or-nothing).
     *
     * @param string[] $bodies
     */
    public function sendBotResponses(BotSession $session, array $bodies): void
    {
        foreach ($bodies as $body) {
            $this->safeSend($session, $body, ConversationMessage::SENDER_BOT);
        }
    }

    /**
     * Manda una respuesta del asesor humano. Mismo log + send pero con sender = advisor.
     */
    public function sendAdvisorMessage(BotSession $session, string $body): ConversationMessage
    {
        return $this->safeSend($session, $body, ConversationMessage::SENDER_ADVISOR);
    }

    private function safeSend(BotSession $session, string $body, string $sender): ConversationMessage
    {
        // Defensa en profundidad — el numero ya debería venir normalizado pero por las dudas.
        $to = PhoneNumber::normalize($session->numero_contacto);

        try {
            $waId = $this->client->sendText($to, $body);
            $msg = $this->engine->logOutbound($session, $body, $waId, $sender);
            $msg->update(['wa_status' => 'sent']);
            return $msg;
        } catch (MetaApiException $e) {
            $msg = $this->engine->logOutbound($session, $body, null, $sender);
            $msg->update(['wa_status' => $e->asWaStatus()]);
            report($e);
            return $msg;
        } catch (Throwable $e) {
            // Cualquier otra cosa (timeout, DNS, etc.) — fallback genérico.
            $msg = $this->engine->logOutbound($session, $body, null, $sender);
            $msg->update(['wa_status' => 'failed']);
            report($e);
            return $msg;
        }
    }
}
