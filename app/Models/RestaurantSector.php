<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantSector extends Model
{
    protected $table = 'restaurant_sectores';

    protected $fillable = [
        'key',
        'label',
        'orden',
        'activo',
        'requiere_capacidad',
    ];

    protected $casts = [
        'activo'             => 'boolean',
        'requiere_capacidad' => 'boolean',
        'orden'              => 'integer',
    ];

    /** Sectores visibles al cliente, en el orden en que se muestran (y se les asigna letra). */
    public static function activos()
    {
        return static::where('activo', true)->orderBy('orden')->get();
    }
}
