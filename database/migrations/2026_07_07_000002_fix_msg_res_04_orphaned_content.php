<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MSG_RES_04 nunca se leía en runtime: el mensaje de sector lo armaba
 * RestaurantCapacity::buildSectorMessage() 100% hardcodeado, ignorando por completo
 * el contenido guardado acá. Cualquier edición previa (incluida una lista de opciones
 * embebida a mano) quedaba huérfana. A partir de ahora MSG_RES_04 SÍ se usa, pero
 * únicamente como la pregunta introductoria — la lista de sectores se arma aparte
 * desde RestaurantSector. Si dejáramos el contenido viejo (que puede incluir su propia
 * lista de opciones) quedaría duplicado con la lista nueva, por eso se resetea acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bot_messages')
            ->where('key', 'MSG_RES_04')
            ->update(['content' => '¿Tenés preferencia de sector?']);
    }

    public function down(): void
    {
        DB::table('bot_messages')
            ->where('key', 'MSG_RES_04')
            ->update(['content' => "¿En qué sector preferís sentarte?\n\n*A.* Salón\n*B.* Galería\n*C.* Terraza\n*D.* Sin preferencia\n\n*0.* Hablar con un asesor"]);
    }
};
