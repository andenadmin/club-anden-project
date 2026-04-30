<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $fillable = ['fecha', 'nombre'];

    protected $casts = ['fecha' => 'date'];

    public static function esFeriado(string $fecha): bool
    {
        // fecha expected as DD/MM/YY
        try {
            $date = \Carbon\Carbon::createFromFormat('d/m/y', $fecha)->format('Y-m-d');
            return self::where('fecha', $date)->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
