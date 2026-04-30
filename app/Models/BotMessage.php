<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotMessage extends Model
{
    protected $fillable = ['key', 'category', 'label', 'content'];

    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
