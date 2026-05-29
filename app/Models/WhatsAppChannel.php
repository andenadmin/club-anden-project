<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppChannel extends Model
{
    protected $table = 'whatsapp_channels';

    protected $fillable = ['slug', 'label', 'phone_number_id', 'access_token', 'default_flow', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function sessions(): HasMany
    {
        return $this->hasMany(BotSession::class, 'channel_id');
    }
}
