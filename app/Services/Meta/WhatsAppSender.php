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
        private readonly WhatsAppClientFactory $factory,
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
            if ((string) $body === '') continue;
            $this->safeSend($session, $body, ConversationMessage::SENDER_BOT);
        }
    }

    /**
     * Envía una plantilla aprobada por Meta al usuario.
     * Útil para re-contactar clientes fuera de la ventana de 24 horas.
     *
     * @param string[] $variables Variables del body en orden
     */
    public function sendTemplate(BotSession $session, string $templateName, string $languageCode, array $variables = []): ConversationMessage
    {
        $to     = PhoneNumber::normalize($session->numero_contacto);
        $client = $this->factory->forSession($session);

        $preview = "[Plantilla: {$templateName}]";
        if (!empty($variables)) {
            $preview .= ' ' . implode(' | ', $variables);
        }

        try {
            $waId = $client->sendTemplate($to, $templateName, $languageCode, $variables);
            $msg  = $this->engine->logOutbound($session, $preview, $waId, ConversationMessage::SENDER_ADVISOR);
            $msg->update(['wa_status' => 'sent']);
            return $msg;
        } catch (MetaApiException $e) {
            $msg = $this->engine->logOutbound($session, $preview, null, ConversationMessage::SENDER_ADVISOR);
            $msg->update(['wa_status' => $e->asWaStatus()]);
            report($e);
            return $msg;
        } catch (Throwable $e) {
            $msg = $this->engine->logOutbound($session, $preview, null, ConversationMessage::SENDER_ADVISOR);
            $msg->update(['wa_status' => 'failed']);
            report($e);
            return $msg;
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
        $to     = PhoneNumber::normalize($session->numero_contacto);
        $client = $this->factory->forSession($session);

        $isImage  = str_starts_with($body, '[IMG]');
        $imageUrl = null;
        $caption  = '';
        if ($isImage) {
            // Formato: [IMG]url  o  [IMG]url||caption
            [$imageUrl, $caption] = array_pad(explode('||', substr($body, 5), 2), 2, '');
        }

        try {
            $waId = $isImage
                ? $client->sendImage($to, $imageUrl, $caption)
                : $client->sendText($to, $body);
            $msg = $this->engine->logOutbound($session, $body, $waId, $sender);
            $msg->update(['wa_status' => 'sent']);
            return $msg;
        } catch (MetaApiException $e) {
            $msg = $this->engine->logOutbound($session, $body, null, $sender);
            $msg->update(['wa_status' => $e->asWaStatus()]);
            report($e);
            return $msg;
        } catch (Throwable $e) {
            $msg = $this->engine->logOutbound($session, $body, null, $sender);
            $msg->update(['wa_status' => 'failed']);
            report($e);
            return $msg;
        }
    }
}
