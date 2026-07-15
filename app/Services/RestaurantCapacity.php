<?php

namespace App\Services;

use App\Models\PanelNotification;
use App\Models\RestaurantCapacityOverride;
use App\Models\RestaurantConfig;
use App\Models\RestaurantSector;
use App\Models\Reserva;
use Carbon\Carbon;

class RestaurantCapacity
{
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
     * El cierre solo aplica a la fecha en que se cerró — fechas futuras no se ven afectadas.
     * Auto-reabre si la fecha de cierre es anterior a hoy.
     */
    public static function estaCerrado(string $sectorKey, ?string $fechaBotFormat = null): bool
    {
        $config = RestaurantConfig::get();

        $fechaCierre = $config->sectores_cerrado_fecha;

        // Auto-reopen: si el sector fue cerrado un día anterior, reabrirlo
        if ($fechaCierre && Carbon::parse($fechaCierre)->lt(Carbon::today())) {
            RestaurantConfig::clearCache();
            $config->forceFill([
                'salon_cerrado'          => false,
                'galeria_cerrado'        => false,
                'terraza_cerrado'        => false,
                'parrilla_cerrado'       => false,
                'patio_cerrado'          => false,
                'sectores_cerrado_fecha' => null,
            ])->save();
            RestaurantConfig::clearCache();
            return false;
        }

        // Si la reserva es para una fecha posterior al día de cierre, el sector está disponible
        if ($fechaBotFormat && $fechaCierre) {
            try {
                $fechaReserva = Carbon::createFromFormat('d/m/y', $fechaBotFormat);
                if ($fechaReserva && $fechaReserva->gt(Carbon::parse($fechaCierre))) {
                    return false;
                }
            } catch (\Throwable) {}
        }

        return (bool) ($config->{"{$sectorKey}_cerrado"} ?? false);
    }

    /**
     * Verifica si un sector tiene capacidad para $personasNuevas personas más.
     * Devuelve true si hay lugar (y no está cerrado).
     */
    public static function tieneCapacidad(string $sectorKey, string $fechaBotFormat, int $personasNuevas): bool
    {
        if (self::estaCerrado($sectorKey, $fechaBotFormat)) {
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
        $umbral    = (int) floor($limite * $alertaPct / 100);
        $usadas    = self::personasEnSector($sectorKey, $fechaBotFormat);

        if ($usadas < $umbral) return;

        // No crear notificación duplicada para el mismo sector hoy
        $yaExiste = PanelNotification::where('tipo', 'sector_alerta')
            ->where('leida', false)
            ->whereRaw("json_extract(payload, '$.sector_key') = ?", [$sectorKey])
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($yaExiste) return;

        $sectorLabel = RestaurantSector::where('key', $sectorKey)->value('label') ?? $sectorKey;

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
     * Construye el texto del mensaje de selección de sector (MSG_RES_04),
     * marcando con "(sin capacidad)" los sectores llenos o cerrados.
     *
     * Los sectores (label, orden, activo) salen de RestaurantSector — editables
     * desde el panel — y la letra se asigna acá según ese mismo orden, así el
     * mensaje que se muestra y el parser que interpreta la respuesta (ver
     * BotMessages::sectorRestaurante) siempre están sincronizados por construcción.
     */
    public static function buildSectorMessage(string $fecha, int $personas): string
    {
        $sectores = RestaurantSector::activos();
        $lines    = [];

        foreach ($sectores as $i => $sector) {
            $letra = chr(65 + $i); // A, B, C...
            if ($sector->requiere_capacidad) {
                $disponible = self::tieneCapacidad($sector->key, $fecha, $personas);
                $suffix     = $disponible ? '' : ' _(sin capacidad)_';
            } else {
                $suffix = '';
            }
            $lines[] = "*{$letra}.* {$sector->label}{$suffix}";
        }

        $intro = BotMessages::render('MSG_RES_04');
        if ($intro === '') {
            $intro = BotMessages::hardcodedDefault('MSG_RES_04') ?? '¿Tenés preferencia de sector?';
        }

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
     * Devuelve la clave interna (fija) del sector a partir de su etiqueta actual.
     * Los sectores con requiere_capacidad=false (ej. "Sin preferencia") devuelven null:
     * no tienen cupo propio, así que no se chequea capacidad para ellos.
     */
    public static function sectorKey(string $label): ?string
    {
        $sector = RestaurantSector::where('label', $label)->first();
        if (!$sector) return null;
        return $sector->requiere_capacidad ? $sector->key : null;
    }
}
