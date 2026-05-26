<?php

use App\Models\BotMessage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $content = "*PRECIOS DE CANCHAS – EL ANDÉN CLUB* 🔥\n*📍 Yerbal 1201 – Caballito*\n🥅⚽🎾🏑🙏🏻🙌🏼\n\n*🎾 PÁDEL MURO*\n\n*Lun a Vie • 7 a 16 hs*\n1h 👉 \$28.000\n1h30 👉 \$42.000\n2h 👉 \$56.000\n\n*Lun a Vie • 16 a 00 hs*\n1h 👉 \$38.000\n1h30 👉 \$57.000\n2h 👉 \$76.000\n\n*Sábados • Domingos • Feriados*\n1h 👉 \$38.000\n1h30 👉 \$57.000\n2h 👉 \$76.000\n\n*🎾 PÁDEL BLINDEX*\n\n*7 a 17 hs*\n1h 👉 \$35.000\n1h30 👉 \$52.000\n2h 👉 \$70.000\n\n*17 a 00 hs*\n1h 👉 \$47.000\n1h30 👉 \$70.000\n2h 👉 \$94.000\n\n*Sábados • Domingos • Feriados*\n1h 👉 \$47.000\n1h30 👉 \$70.000\n2h 👉 \$94.000\n\n*FÚTBOL 5 ⚽/ HOCKEY 🏑 (Arena)*\n\n*Hasta 16 hs*\nArena 👉 \$55.000 🏑\nCaucho 👉 \$70.000\n\n*Desde 16 hs*\nArena 👉 \$75.000 🏑\nCaucho 👉 \$85.000\n\n*🎾 TENIS*\nLun a Vie 8 a 16 hs 👉 \$40.000\nLun a Vie 16 a 17 hs 👉 \$75.000\nSáb/Dom 8 a 14 hs 👉 \$55.000\n\n*⚽ FÚTBOL 8*\nLun a Vie mañana 👉 \$160.000\nLun a Vie tarde/noche 👉 \$224.000\nSáb/Dom/Feriados 👉 \$224.000\n\n📲 Reservas online en los *próximos 7 días*:\nhttps://atcsports.io/venues/el-anden-caba\n\n📞 WhatsApp: *Para reservas especiales*\n11 7182-1201\n\n⚠️ *Menores de 16 años:*\nLas reservas tienen protocolos especiales y requieren supervisión del club.";

        BotMessage::firstOrCreate(
            ['key' => 'MSG_DEPORTES_PRECIOS_CANCHAS'],
            [
                'category'    => 'deportes',
                'label'       => 'Lista de precios de canchas',
                'content'     => $content,
                'is_archived' => false,
            ],
        );
    }

    public function down(): void
    {
        BotMessage::where('key', 'MSG_DEPORTES_PRECIOS_CANCHAS')->delete();
    }
};
