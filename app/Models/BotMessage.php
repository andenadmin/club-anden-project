<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotMessage extends Model
{
    protected $fillable = ['key', 'category', 'label', 'content', 'is_archived'];

    protected $casts = ['is_archived' => 'boolean'];

    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->where('is_archived', false)->first();
    }
}
