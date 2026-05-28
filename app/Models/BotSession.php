<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotSession extends Model
{
    protected $fillable = [
        'channel_id',
        'numero_contacto',
        'estado_actual',
        'rama_activa',
        'subtipo_activo',
        'current_step',
        'contador_invalidos',
        'datos_parciales',
        'id_cliente',
        'timestamp_pausa',
        'motivo_pausa',
        'estado_previo_pausa',
        'next_resume_check_at',
        'resolved_by_advisor_at',
        'last_message_at',
        'unread_count',
        'is_pinned',
        'is_archived',
        'is_important',
        'pinned_at',
        'important_at',
    ];

    protected $casts = [
        'datos_parciales'         => 'array',
        'timestamp_pausa'         => 'datetime',
        'next_resume_check_at'    => 'datetime',
        'resolved_by_advisor_at'  => 'datetime',
        'last_message_at'         => 'datetime',
        'pinned_at'               => 'datetime',
        'important_at'            => 'datetime',
        'contador_invalidos'      => 'integer',
        'unread_count'            => 'integer',
        'is_pinned'               => 'boolean',
        'is_archived'             => 'boolean',
        'is_important'            => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChannel::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function getDatos(string $key, mixed $default = null): mixed
    {
        return $this->datos_parciales[$key] ?? $default;
    }

    public function setDato(string $key, mixed $value): void
    {
        $datos = $this->datos_parciales ?? [];
        $datos[$key] = $value;
        $this->datos_parciales = $datos;
        $this->save();
    }

    public function mergeEstado(array $attrs): void
    {
        $this->fill($attrs)->save();
    }
}
