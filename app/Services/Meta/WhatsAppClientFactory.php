<?php

namespace App\Services\Meta;

use App\Models\BotSession;
use App\Models\WhatsAppChannel;

class WhatsAppClientFactory
{
    private array $cache = [];

    public function forChannel(WhatsAppChannel $channel): WhatsAppClient
    {
        if (!isset($this->cache[$channel->id])) {
            $cfg = config('services.whatsapp');
            $this->cache[$channel->id] = new WhatsAppClient(
                phoneNumberId: $channel->phone_number_id,
                accessToken:   $channel->access_token ?? $cfg['access_token'] ?? '',
                apiVersion:    $cfg['api_version'] ?? 'v21.0',
                appSecret:     $cfg['app_secret'] ?? '',
            );
        }
        return $this->cache[$channel->id];
    }

    public function forSession(BotSession $session): WhatsAppClient
    {
        $channel = $session->channel_id ? $session->channel : null;
        $channel ??= WhatsAppChannel::where('is_active', true)->first();

        if ($channel) {
            return $this->forChannel($channel);
        }

        // Ultimate fallback: global config (backward compat)
        $cfg = config('services.whatsapp');
        return new WhatsAppClient(
            phoneNumberId: $cfg['phone_number_id'] ?? '',
            accessToken:   $cfg['access_token'] ?? '',
            apiVersion:    $cfg['api_version'] ?? 'v21.0',
            appSecret:     $cfg['app_secret'] ?? '',
        );
    }

    public function default(): WhatsAppClient
    {
        $channel = WhatsAppChannel::where('is_active', true)->first();
        if ($channel) {
            return $this->forChannel($channel);
        }
        $cfg = config('services.whatsapp');
        return new WhatsAppClient(
            phoneNumberId: $cfg['phone_number_id'] ?? '',
            accessToken:   $cfg['access_token'] ?? '',
            apiVersion:    $cfg['api_version'] ?? 'v21.0',
            appSecret:     $cfg['app_secret'] ?? '',
        );
    }
}
