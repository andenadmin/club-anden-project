<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_messages', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('bot_messages', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }
};
