<?php

namespace App\Services;

use App\Models\PanelNotification;
use App\Models\RestaurantCapacityOverride;
use App\Models\RestaurantConfig;
use App\Models\Reserva;
use Carbon\Carbon;

class RestaurantCapacity
{
    private const SECTOR_KEY = [
        'Salón'           => 'salon',
        'Galería'         => 'galeria',
        'Terraza'         => 'terraza',
        'Parrilla'        => 'parrilla',
        'Sin preferencia' => null,
    ];

    private const SECTOR_LABEL = [
        'salon'   => 'Salón',
        'galeria' => 'Galería',
        'terraza' => 'Terraza',
        'parrilla'=> 'Parrilla',
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
     * Verifica si un sector está marcado como cerrado manualmente por el admin.
     * Auto-reabre si la fecha de cierre es anterior a hoy.
     */
    public static function estaCerrado(string $sectorKey): bool
    {
        $config = RestaurantConfig::get();

        // Auto-reopen: si el sector fue cerrado un día anterior, reabrirlo
        $fechaCierre = $config->sectores_cerrado_fecha;
        if ($fechaCierre && Carbon::parse($fechaCierre)->lt(Carbon::today())) {
            RestaurantConfig::clearCache();
            $config->forceFill([
                'salon_cerrado'          => false,
                'galeria_cerrado'        => false,
                'terraza_cerrado'        => false,
                'parrilla_cerrado'       => false,
                'sectores_cerrado_fecha' => null,
            ])->save();
            RestaurantConfig::clearCache();
            return false;
        }

        return (bool) ($config->{"{$sectorKey}_cerrado"} ?? false);
    }

    /**
     * Verifica si un sector tiene capacidad para $personasNuevas personas más.
     * Devuelve true si hay lugar (y no está cerrado).
     */
    public static function tieneCapacidad(string $sectorKey, string $fechaBotFormat, int $personasNuevas): bool
    {
        if (self::estaCerrado($sectorKey)) {
            return false;
        }

        $limite = self::limiteEfectivoParaSector($sectorKey, $fechaBotFormat);
        if ($limite <= 0) return true;

        $usadas = self::personasEnSector($sectorKey, $fechaBotFormat);
        return ($usadas + $personasNuevas) <= $limite;
    }

    /**
     * Límite efectivo para un sector en una fecha: usa override de fecha si existe,
     * caso contrario cae al config global con capacidad_pct aplicado.
     */
    private static function limiteEfectivoParaSector(string $sectorKey, string $fechaBotFormat): int
    {
        try {
            $carbon   = Carbon::createFromFormat('d/m/y', $fechaBotFormat);
            $override = $carbon ? RestaurantCapacityOverride::forDate($carbon) : null;
            if ($override) {
                $sobreescrito = $override->limiteParaSector($sectorKey);
                if ($sobreescrito !== null) {
                    return $sobreescrito;
                }
            }
        } catch (\Throwable) {
            // fecha mal formateada → caer al config global
        }

        return RestaurantConfig::get()->limiteParaSector($sectorKey);
    }

    /**
     * Verifica si un sector superó el umbral de alerta y emite una PanelNotification
     * si no existe ya una para hoy para ese sector.
     */
    public static function checkAlertaOcupacion(string $sectorKey, string $fechaBotFormat): void
    {
        $config = RestaurantConfig::get();
        $limite = $config->limiteParaSector($sectorKey);
        if ($limite <= 0) return;

        $alertaPct = $config->sector_alerta_pct ?? 70;
        $usadas    = self::personasEnSector($sectorKey, $fechaBotFormat);

        $pctOcupado = ($usadas / $limite) * 100;
        if ($pctOcupado < $alertaPct) return;

        // No crear notificación duplicada para el mismo sector hoy
        $yaExiste = PanelNotification::where('tipo', 'sector_alerta')
            ->where('leida', false)
            ->whereRaw("json_extract(payload, '$.sector_key') = ?", [$sectorKey])
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($yaExiste) return;

        $sectorLabel = self::SECTOR_LABEL[$sectorKey] ?? $sectorKey;

        PanelNotification::create([
            'tipo'    => 'sector_alerta',
            'payload' => [
                'mensaje'       => "Alcanzamos el {$alertaPct}% de la capacidad en *{$sectorLabel}*. ¿Querés que informemos a quienes reservan que no hay más cupo?",
                'sector_key'    => $sectorKey,
                'sector_label'  => $sectorLabel,
            ],
            'leida' => false,
        ]);
    }

    /**
     * Construye el texto del mensaje de selección de sector (MSG_RES_04 dinámico),
     * marcando con "(sin capacidad)" los sectores llenos o cerrados.
     */
    public static function buildSectorMessage(string $fecha, int $personas): string
    {
        $opciones = [
            'A' => ['label' => 'Salón',   'key' => 'salon'],
            'B' => ['label' => 'Galería', 'key' => 'galeria'],
            'C' => ['label' => 'Terraza', 'key' => 'terraza'],
            'D' => ['label' => 'Parrilla','key' => 'parrilla'],
        ];

        $lines      = [];
        $todosLlenos = true;

        foreach ($opciones as $letra => $opt) {
            $disponible = self::tieneCapacidad($opt['key'], $fecha, $personas);
            if ($disponible) $todosLlenos = false;
            $suffix = $disponible ? '' : ' _(sin capacidad)_';
            $lines[] = "*{$letra}.* {$opt['label']}{$suffix}";
        }

        $lines[] = '*E.* Sin preferencia';

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
