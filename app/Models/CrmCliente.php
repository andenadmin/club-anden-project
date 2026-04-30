<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCliente extends Model
{
    protected $table = 'crm_clientes';

    protected $fillable = [
        'id_cliente',
        'etiquetas',
        'canal_captacion',
        'notas',
        'opt_in_marketing',
        'valor_lifetime',
        'fecha_ultimo_evento',
    ];

    protected $casts = [
        'etiquetas'          => 'array',
        'opt_in_marketing'   => 'boolean',
        'valor_lifetime'     => 'decimal:2',
        'fecha_ultimo_evento' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
