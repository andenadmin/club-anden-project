<?php

use App\Models\BotMessage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        BotMessage::where('key', 'MSG_DEP_01')->update([
            'content' => "Contamos con canchas de Fútbol 5 y 8, Pádel y Tenis, podés ver las que quedan disponibles y reservar en https://atcsports.io/venues/el-anden-caba.\n\n💰 Para ver la *lista de precios*, respondé *ver precios*.\n\nLas canchas están disponibles de *8 a 24 hs*.\n🏢 Oficina deportiva (atención al público): *8 a 23.30 hs*.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\nEscribí *0* para hablar con un asesor, o *atrás* para volver al menú principal.",
        ]);
    }

    public function down(): void
    {
        BotMessage::where('key', 'MSG_DEP_01')->update([
            'content' => "Contamos con canchas de Fútbol 5 y 8, Pádel y Tenis, podés ver las que quedan disponibles y reservar en https://atcsports.io/venues/el-anden-caba.\n\nLas canchas están disponibles de *8 a 24 hs*.\n🏢 Oficina deportiva (atención al público): *8 a 23.30 hs*.\n\n📍 *Cómo llegar:*\n• Estacionamiento gratuito: Yerbal 1201\n• Entrada peatonal: Yerbal 1255\n\nEscribí *0* para hablar con un asesor, o *atrás* para volver al menú principal.",
        ]);
    }
};
