<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostoEvento extends Model
{
    protected $table = 'costos_eventos';

    protected $fillable = ['concepto', 'descripcion', 'precio'];

    protected $casts = ['precio' => 'decimal:2'];

    private static array $allPrecios = [];
    private static bool  $preciosLoaded = false;

    private static function loadPrecios(): void
    {
        if (self::$preciosLoaded) return;
        self::$preciosLoaded = true;
        self::$allPrecios = static::pluck('precio', 'concepto')
            ->map(fn ($p) => (float) $p)
            ->all();
    }

    public static function precio(string $concepto): float
    {
        self::loadPrecios();
        return self::$allPrecios[$concepto] ?? 0.0;
    }
}
