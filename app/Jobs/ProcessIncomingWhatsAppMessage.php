<?php

namespace App\Jobs;

use App\Models\BotSession;
use App\Models\ConversationMessage;
use App\Models\WhatsAppChannel;
use App\Services\BotMessages;
use App\Services\BotEngine;
use App\Services\Meta\WhatsAppClientFactory;
use App\Services\Meta\WhatsAppSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Tipos de mensaje que NO son texto y deben recibir MSG_OPCION_INVALIDA. */
    private const NON_TEXT_TYPES = [
        'sticker', 'audio', 'image', 'video', 'document', 'location', 'contacts', 'reaction',
    ];

    public function __construct(
        public readonly string $from,
        public readonly string $body,
        public readonly string $waMessageId,
        public readonly string $messageType = 'text',
        public readonly ?int $channelId = null,
    ) {}

    /**
     * Serializa el procesamiento por número: evita que dos mensajes del mismo
     * contacto se procesen en paralelo (workers concurrentes) y corrompan
     * current_step/contador_invalidos con un "último que guarda gana".
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->from))->releaseAfter(3)->expireAfter(30)];
    }

    public function handle(BotEngine $engine, WhatsAppSender $sender, WhatsAppClientFactory $factory): void
    {
        // §8b — Debounce anti-spam: evita que Meta reenvíe el mismo webhook dos veces en rápida sucesión.
        // La clave incluye el wa_message_id para no bloquear mensajes distintos del mismo usuario.
        if (!Cache::add("debounce:{$this->from}:{$this->waMessageId}", true, 30)) {
            return;
        }

        // Idempotencia: si ya procesamos este wa_message_id, salir.
        if (ConversationMessage::where('wa_message_id', $this->waMessageId)->exists()) {
            return;
        }

        // Resolve the WhatsApp client for this channel
        $client = $this->channelId
            ? $factory->forChannel(WhatsAppChannel::find($this->channelId))
            : $factory->default();

        // Marcar como leído (doble tilde azul en el lado del usuario)
        $client->markAsRead($this->waMessageId);

        // §8a — Mensajes no-texto: se registran igual en el inbox (con el placeholder que
        // arma el webhook, ej. "[Audio]") para que no desaparezcan de la conversación, pero
        // responden con opción inválida sin avanzar estado ni tocar contador_invalidos.
        if (in_array($this->messageType, self::NON_TEXT_TYPES, true)) {
            $session = BotSession::firstOrCreate(
                ['numero_contacto' => $this->from],
                ['estado_actual' => 'INICIO', 'datos_parciales' => [], 'contador_invalidos' => 0, 'channel_id' => $this->channelId]
            );
            $engine->logInbound($session, $this->body);
            $session->messages()
                ->where('direction', ConversationMessage::DIRECTION_INBOUND)
                ->where('sender', ConversationMessage::SENDER_USER)
                ->whereNull('wa_message_id')
                ->latest('id')
                ->limit(1)
                ->update(['wa_message_id' => $this->waMessageId]);

            $invalidMsg = BotMessages::render('MSG_OPCION_INVALIDA');
            $sender->sendBotResponses($session, [$invalidMsg]);
            return;
        }

        $responses = $engine->process($this->from, $this->body, $this->channelId);

        // Marco el último inbound creado por process() con el wa_message_id de Meta
        // para idempotencia futura. process() crea el row sin wa_message_id porque no lo conoce.
        $session = $engine->lastSession();
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
