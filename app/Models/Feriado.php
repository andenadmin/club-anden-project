<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $fillable = ['fecha', 'nombre'];

    protected $casts = ['fecha' => 'date'];

    private static ?array $allFeriados = null;

    public static function esFeriado(string $fecha): bool
    {
        try {
            $date = \Carbon\Carbon::createFromFormat('d/m/y', $fecha)->format('Y-m-d');
            if (self::$allFeriados === null) {
                self::$allFeriados = static::pluck('fecha')->map(fn ($f) => (string) $f)->all();
            }
            return in_array($date, self::$allFeriados, true);
        } catch (\Throwable) {
            return false;
        }
    }
}
