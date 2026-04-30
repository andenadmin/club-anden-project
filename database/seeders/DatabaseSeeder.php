<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'administracionadn2026@gmail.com'],
            [
                'name'              => 'Administración Anden',
                'password'          => bcrypt('ANDEN2026_BOT'),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            CostosEventosSeeder::class,
            FeriadosSeeder::class,
            BotMessagesSeeder::class,
        ]);
    }
}
