<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostoEvento extends Model
{
    protected $table = 'costos_eventos';

    protected $fillable = ['concepto', 'descripcion', 'precio'];

    protected $casts = ['precio' => 'decimal:2'];

    public static function precio(string $concepto): float
    {
        return (float) self::where('concepto', $concepto)->value('precio') ?? 0;
    }
}
