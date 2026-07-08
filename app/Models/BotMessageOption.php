<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotMessageOption extends Model
{
    protected $table = 'bot_message_options';

    protected $fillable = [
        'options_key',
        'value',
        'label',
        'orden',
        'activo',
        'meta',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
        'meta'   => 'array',
    ];

    /** Opciones visibles al cliente para un menú, en el orden en que se muestran (y se les asigna letra/número). */
    public static function activos(string $optionsKey)
    {
        return static::where('options_key', $optionsKey)->where('activo', true)->orderBy('orden')->get();
    }
}
