<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelNotification extends Model
{
    protected $table = 'panel_notifications';

    protected $fillable = [
        'tipo',
        'payload',
        'leida',
    ];

    protected $casts = [
        'payload' => 'array',
        'leida'   => 'boolean',
    ];
}
