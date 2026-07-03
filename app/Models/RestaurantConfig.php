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
        'parrilla_capacidad',
        'patio_capacidad',
        'capacidad_pct',
        'sector_alerta_pct',
        'reserva_hora_desde',
        'reserva_hora_hasta',
        'salon_cerrado',
        'galeria_cerrado',
        'terraza_cerrado',
        'parrilla_cerrado',
        'patio_cerrado',
        'sectores_cerrado_fecha',
    ];

    protected $casts = [
        'salon_cerrado'    => 'boolean',
        'galeria_cerrado'  => 'boolean',
        'terraza_cerrado'  => 'boolean',
        'parrilla_cerrado' => 'boolean',
        'patio_cerrado'    => 'boolean',
    ];

    private static ?self $cached = null;

    public static function get(): self
    {
        if (self::$cached === null) {
            self::$cached = static::first() ?? new self([
                'salon_capacidad'    => 52,
                'galeria_capacidad'  => 69,
                'terraza_capacidad'  => 20,
                'parrilla_capacidad' => 20,
                'patio_capacidad'    => 79,
                'capacidad_pct'      => 100,
                'sector_alerta_pct'  => 70,
                'reserva_hora_desde' => 9,
                'reserva_hora_hasta' => 22,
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
            'parrilla'=> $this->parrilla_capacidad,
            'patio'   => $this->patio_capacidad,
            default   => 0,
        };
        return (int) floor($cap * $this->capacidad_pct / 100);
    }
}
