<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSession extends Model
{
    protected $fillable = [
        'numero_contacto',
        'estado_actual',
        'rama_activa',
        'subtipo_activo',
        'current_step',
        'contador_invalidos',
        'datos_parciales',
        'id_cliente',
        'timestamp_pausa',
    ];

    protected $casts = [
        'datos_parciales'  => 'array',
        'timestamp_pausa'  => 'datetime',
        'contador_invalidos' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
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
