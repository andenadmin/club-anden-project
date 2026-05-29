<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RestaurantCapacityOverride extends Model
{
    protected $table = 'restaurant_capacity_overrides';

    protected $fillable = ['fecha', 'salon_max', 'galeria_max', 'terraza_max', 'parrilla_max'];

    protected $casts = ['fecha' => 'date'];

    public static function forDate(Carbon $date): ?self
    {
        return static::whereDate('fecha', $date->toDateString())->first();
    }

    public function limiteParaSector(string $sector): ?int
    {
        return match ($sector) {
            'salon'    => $this->salon_max,
            'galeria'  => $this->galeria_max,
            'terraza'  => $this->terraza_max,
            'parrilla' => $this->parrilla_max,
            default    => null,
        };
    }
}
