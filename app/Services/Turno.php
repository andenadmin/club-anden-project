<?php

namespace App\Services;

final class Turno
{
    public function __construct(
        public readonly string $hora,
        public readonly int $desde,
        public readonly ?int $hasta,
        public readonly string $label,
    ) {}

    /**
     * "Turno mediodía completo: 12 hs (hasta las 16 hs — con menú fijo)" → 12:00 a 16:00.
     * "Turno noche 1: 20 hs" → 20:00 sin hora de fin.
     * La hora de inicio se busca antes de "hasta" para que la de fin no la pise.
     */
    public static function fromLabel(string $label): ?self
    {
        $partes = preg_split('/\bhasta\b/iu', $label, 2);
        $desde  = self::horaEnTexto($partes[0]);
        if ($desde === null) return null;

        $hasta = isset($partes[1]) ? self::horaEnTexto($partes[1]) : null;
        if ($hasta !== null && $hasta <= $desde) $hasta = null;

        return new self(sprintf('%02d:%02d', intdiv($desde, 60), $desde % 60), $desde, $hasta, trim($label));
    }

    /** "20:00" / "20.30" / "20" → minutos desde las 00:00. */
    public static function minutos(string $hora): ?int
    {
        if (!preg_match('/^\s*(\d{1,2})(?:[:.](\d{2}))?/', $hora, $m)) return null;
        $h  = (int) $m[1];
        $mm = isset($m[2]) ? (int) $m[2] : 0;
        if ($h > 23 || $mm > 59) return null;

        return $h * 60 + $mm;
    }

    private static function horaEnTexto(string $texto): ?int
    {
        if (preg_match('/(\d{1,2})[.,:](\d{2})\s*hs/iu', $texto, $m)
            || preg_match('/(\d{1,2})\s*hs/iu', $texto, $m)) {
            $h  = (int) $m[1];
            $mm = isset($m[2]) ? (int) $m[2] : 0;
            if ($h <= 23 && $mm <= 59) return $h * 60 + $mm;
        }

        return null;
    }
}
