<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    public const DIRECTION_INBOUND  = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const SENDER_USER    = 'user';
    public const SENDER_BOT     = 'bot';
    public const SENDER_ADVISOR = 'advisor';

    protected $fillable = [
        'bot_session_id',
        'direction',
        'sender',
        'body',
        'wa_message_id',
        'wa_status',
    ];

    public function botSession(): BelongsTo
    {
        return $this->belongsTo(BotSession::class);
    }
}
