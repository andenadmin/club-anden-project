<?php

namespace App\Jobs;

use App\Models\BotSession;
use App\Models\ConversationMessage;
use App\Services\BotEngine;
use App\Services\Meta\WhatsAppSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $from,
        public readonly string $body,
        public readonly string $waMessageId,
    ) {}

    public function handle(BotEngine $engine, WhatsAppSender $sender): void
    {
        // Idempotencia: si ya procesamos este wa_message_id, salir.
        if (ConversationMessage::where('wa_message_id', $this->waMessageId)->exists()) {
            return;
        }

        $responses = $engine->process($this->from, $this->body);

        // Marco el último inbound creado por process() con el wa_message_id de Meta
        // para idempotencia futura. process() crea el row sin wa_message_id porque no lo conoce.
        $session = BotSession::where('numero_contacto', $this->from)->first();
        if ($session) {
            $session->messages()
                ->where('direction', ConversationMessage::DIRECTION_INBOUND)
                ->where('sender', ConversationMessage::SENDER_USER)
                ->whereNull('wa_message_id')
                ->latest('id')
                ->limit(1)
                ->update(['wa_message_id' => $this->waMessageId]);

            $sender->sendBotResponses($session, $responses);
        }
    }
}
