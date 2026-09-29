<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WhatsAppFlowToken extends Model
{
    public const FLOW_RESERVA = 'reserva_restaurante';

    protected $table = 'whatsapp_flow_tokens';

    protected $fillable = [
        'token',
        'bot_session_id',
        'flow',
        'expires_at',
        'completed_at',
        'resultado',
        'reserva_id',
        'confirmation_sent_at',
    ];

    protected $casts = [
        'expires_at'           => 'datetime',
        'completed_at'         => 'datetime',
        'confirmation_sent_at' => 'datetime',
    ];

    public static function emitir(BotSession $session, string $flow, int $ttlHoras): self
    {
        return static::create([
            'token'          => Str::random(48),
            'bot_session_id' => $session->id,
            'flow'           => $flow,
            'expires_at'     => now()->addHours($ttlHoras),
        ]);
    }

    public static function vigente(?string $token, string $flow): ?self
    {
        if (!$token) return null;

        return static::where('token', $token)
            ->where('flow', $flow)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BotSession::class, 'bot_session_id');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
