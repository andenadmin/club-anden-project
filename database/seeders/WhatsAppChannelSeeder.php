<?php

namespace Database\Seeders;

use App\Models\WhatsAppChannel;
use Illuminate\Database\Seeder;

class WhatsAppChannelSeeder extends Seeder
{
    public function run(): void
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        if (!$phoneNumberId) return;

        WhatsAppChannel::firstOrCreate(
            ['phone_number_id' => $phoneNumberId],
            [
                'slug'         => 'restaurante',
                'label'        => 'Restaurante',
                'access_token' => null,
                'default_flow' => null,
                'is_active'    => true,
            ]
        );
    }
}
