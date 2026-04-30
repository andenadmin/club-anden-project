<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reserva extends Model
{
    protected $fillable = [
        'id_cliente',
        'rama_servicio',
        'subtipo',
        'estado_reserva',
        'datos',
        'presupuesto_total',
        'tiene_extras',
    ];

    protected $casts = [
        'datos'         => 'array',
        'tiene_extras'  => 'boolean',
        'presupuesto_total' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
