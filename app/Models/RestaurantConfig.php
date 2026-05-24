<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantConfig extends Model
{
    protected $table = 'restaurant_config';

    protected $fillable = [
        'salon_capacidad',
        'galeria_capacidad',
        'terraza_capacidad',
        'capacidad_pct',
    ];

    private static ?self $cached = null;

    public static function get(): self
    {
        if (self::$cached === null) {
            self::$cached = static::first() ?? new self([
                'salon_capacidad'   => 50,
                'galeria_capacidad' => 60,
                'terraza_capacidad' => 60,
                'capacidad_pct'     => 70,
            ]);
        }
        return self::$cached;
    }

    public static function clearCache(): void
    {
        self::$cached = null;
    }

    public function limiteParaSector(string $sector): int
    {
        $cap = match ($sector) {
            'salon'   => $this->salon_capacidad,
            'galeria' => $this->galeria_capacidad,
            'terraza' => $this->terraza_capacidad,
            default   => 0,
        };
        return (int) floor($cap * $this->capacidad_pct / 100);
    }
}
