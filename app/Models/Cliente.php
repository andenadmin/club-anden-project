<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = [
        'numero_contacto',
        'nombre_cliente',
        'mail_contacto',
        'contador_reservas_deportes',
        'contador_reservas_restaurante',
        'contador_reservas_eventos',
    ];

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'id_cliente');
    }

    public function botSession(): HasMany
    {
        return $this->hasMany(BotSession::class, 'id_cliente');
    }

    public function crm(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CrmCliente::class, 'id_cliente');
    }

    public function crmOrCreate(): CrmCliente
    {
        return $this->crm ?? $this->crm()->create();
    }
}
