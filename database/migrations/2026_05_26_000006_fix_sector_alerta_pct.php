<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('restaurant_config')->update(['sector_alerta_pct' => 70]);
    }

    public function down(): void
    {
        DB::table('restaurant_config')->update(['sector_alerta_pct' => 100]);
    }
};
