<?php

namespace App\Services;

use App\Models\RestaurantConfig;
use App\Models\Reserva;
use Carbon\Carbon;

class RestaurantCapacity
{
    // Mapeo etiqueta → clave interna del sector
    private const SECTOR_KEY = [
        'Salón'           => 'salon',
        'Galería'         => 'galeria',
        'Terraza'         => 'terraza',
        'Sin preferencia' => null,
    ];

    /**
     * Personas ya reservadas en un sector para una fecha dada.
     * Solo cuenta reservas CONFIRMADA y PENDIENTE_CONFIRMACION.
     */
    public static function personasEnSector(string $sectorKey, string $fechaBotFormat): int
    {
        $total = 0;

        Reserva::where('rama_servicio', 'RESTAURANTE')
            ->whereIn('estado_reserva', ['CONFIRMADA', 'PENDIENTE_CONFIRMACION'])
            ->whereRaw("json_extract(datos, '$.fecha') = ?", [$fechaBotFormat])
            ->whereRaw("json_extract(datos, '$.sector_key') = ?", [$sectorKey])
            ->each(function (Reserva $r) use (&$total) {
                $total += self::extractPersonas($r->datos['numero_personas'] ?? '');
            });

        return $total;
    }

    /**
     * Verifica si un sector tiene capacidad para $personasNuevas personas más.
     * Devuelve true si hay lugar.
     */
    public static function tieneCapacidad(string $sectorKey, string $fechaBotFormat, int $personasNuevas): bool
    {
        $config = RestaurantConfig::get();
        $limite = $config->limiteParaSector($sectorKey);
        if ($limite <= 0) return true;

        $usadas = self::personasEnSector($sectorKey, $fechaBotFormat);
        return ($usadas + $personasNuevas) <= $limite;
    }

    /**
     * Construye el texto del mensaje de selección de sector (MSG_RES_04 dinámico),
     * marcando con "(sin capacidad)" los sectores llenos.
     */
    public static function buildSectorMessage(string $fecha, int $personas): string
    {
        $opciones = [
            'A' => ['label' => 'Salón',   'key' => 'salon'],
            'B' => ['label' => 'Galería', 'key' => 'galeria'],
            'C' => ['label' => 'Terraza', 'key' => 'terraza'],
        ];

        $lines = [];
        $todosLlenos = true;

        foreach ($opciones as $letra => $opt) {
            $disponible = self::tieneCapacidad($opt['key'], $fecha, $personas);
            if ($disponible) $todosLlenos = false;
            $suffix = $disponible ? '' : ' _(sin capacidad)_';
            $lines[] = "*{$letra}.* {$opt['label']}{$suffix}";
        }

        $lines[] = '*D.* Sin preferencia';

        $base = BotMessages::render('MSG_RES_04');

        // Si el mensaje del admin tiene el texto base, lo usamos como encabezado
        // Sino usamos uno por defecto
        $intro = "¿En qué sector preferís sentarte?";

        return $intro . "\n\n" . implode("\n", $lines) . "\n\n*0.* Hablar con un asesor";
    }

    /**
     * Extrae el número máximo de personas de un string como "3 a 4 personas" o "5 personas".
     */
    public static function extractPersonas(string $str): int
    {
        if (!$str) return 0;
        if (preg_match('/(\d+)\s*a\s*(\d+)/i', $str, $m)) return (int) $m[2];
        if (preg_match('/(\d+)/', $str, $m)) return (int) $m[1];
        return 0;
    }

    /**
     * Devuelve la clave interna del sector a partir de su etiqueta.
     */
    public static function sectorKey(string $label): ?string
    {
        return self::SECTOR_KEY[$label] ?? null;
    }
}
