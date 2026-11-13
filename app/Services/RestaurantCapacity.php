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
     * Ventana de ocupación: ±1 hora del inicio de la reserva (= rango de ~2 hs).
     * Así 13:00 / 13:15 / 13:30 comparten el mismo cupo; 12:00 y 14:00 no.
     */
    public const VENTANA_OCUPACION_MINUTOS = 60;

    /**
     * Personas ya reservadas en un sector para una fecha (y opcionalmente una ventana horaria).
     * Solo cuenta reservas CONFIRMADA y PENDIENTE_CONFIRMACION.
     *
     * Si $hora está definida, solo suma reservas cuyo horario cae a ±1h de esa hora.
     * Reservas sin hora se cuentan igual (conservador: ocupan cupo en cualquier ventana).
     * Si $hora es null, cuenta el día completo (compat / tools de test).
     */
    public static function personasEnSector(string $sectorKey, string $fechaBotFormat, ?string $hora = null): int
    {
        $total     = 0;
        $anchorMin = self::parseHoraToMinutes($hora);

        Reserva::where('rama_servicio', 'RESTAURANTE')
            ->whereIn('estado_reserva', ['CONFIRMADA', 'PENDIENTE_CONFIRMACION'])
            ->whereRaw("json_extract(datos, '$.fecha') = ?", [$fechaBotFormat])
            ->whereRaw("json_extract(datos, '$.sector_key') = ?", [$sectorKey])
            ->each(function (Reserva $r) use (&$total, $anchorMin) {
                if ($anchorMin !== null) {
                    $rMin = self::parseHoraToMinutes($r->datos['hora'] ?? null);
                    if ($rMin !== null && abs($rMin - $anchorMin) > self::VENTANA_OCUPACION_MINUTOS) {
                        return;
                    }
                }
                $total += self::extractPersonas($r->datos['numero_personas'] ?? '');
            });

        return $total;
    }

    /**
     * Verifica si un sector está marcado como cerrado manualmente por el admin.
     * El cierre aplica SOLO a la fecha de la alerta (ej. el sábado de la reserva),
     * no a los días previos ni posteriores.
     * Auto-reabre si la fecha de cierre ya pasó.
     */
    public static function estaCerrado(string $sectorKey, ?string $fechaBotFormat = null): bool
    {
        $config = RestaurantConfig::get();

        $fechaCierre = $config->sectores_cerrado_fecha;
        $cerradoFlag = (bool) ($config->{"{$sectorKey}_cerrado"} ?? false);

        if (!$cerradoFlag || !$fechaCierre) {
            return false;
        }

        // Auto-reopen: si el día de cierre ya pasó, limpiar flags
        if (Carbon::parse($fechaCierre)->lt(Carbon::today())) {
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

        // Sin fecha de reserva no podemos afirmar el cierre puntual
        if (!$fechaBotFormat) {
            return false;
        }

        try {
            $fechaReserva = Carbon::createFromFormat('d/m/y', $fechaBotFormat)->startOfDay();
            $cierre       = Carbon::parse($fechaCierre)->startOfDay();
            return $fechaReserva->equalTo($cierre);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Verifica si un sector tiene capacidad para $personasNuevas personas más
     * en la ventana horaria de $hora (±1h). Devuelve true si hay lugar (y no está cerrado).
     *
     * Nota: el panel de reservas manuales NO usa este check — puede sobrecargar a propósito.
     */
    public static function tieneCapacidad(
        string $sectorKey,
        string $fechaBotFormat,
        int $personasNuevas,
        ?string $hora = null,
    ): bool {
        if (self::estaCerrado($sectorKey, $fechaBotFormat)) {
            return false;
        }

        $limite = self::limiteEfectivoParaSector($sectorKey, $fechaBotFormat);
        if ($limite <= 0) return true;

        $usadas = self::personasEnSector($sectorKey, $fechaBotFormat, $hora);
        return ($usadas + $personasNuevas) <= $limite;
    }

    /**
     * Personas ya reservadas en TODO el restaurante para una fecha/ventana
     * (cualquier sector, incluido "Sin preferencia" sin sector_key).
     */
    public static function personasEnRestaurante(string $fechaBotFormat, ?string $hora = null): int
    {
        $total     = 0;
        $anchorMin = self::parseHoraToMinutes($hora);

        Reserva::where('rama_servicio', 'RESTAURANTE')
            ->whereIn('estado_reserva', ['CONFIRMADA', 'PENDIENTE_CONFIRMACION'])
            ->whereRaw("json_extract(datos, '$.fecha') = ?", [$fechaBotFormat])
            ->each(function (Reserva $r) use (&$total, $anchorMin) {
                if ($anchorMin !== null) {
                    $rMin = self::parseHoraToMinutes($r->datos['hora'] ?? null);
                    if ($rMin !== null && abs($rMin - $anchorMin) > self::VENTANA_OCUPACION_MINUTOS) {
                        return;
                    }
                }
                $total += self::extractPersonas($r->datos['numero_personas'] ?? '');
            });

        return $total;
    }

    /**
     * Suma de límites efectivos de todos los sectores con control de cupo.
     */
    public static function limiteTotalRestaurante(string $fechaBotFormat): int
    {
        $total = 0;
        foreach (RestaurantSector::activos()->where('requiere_capacidad', true) as $sector) {
            $total += self::limiteEfectivoParaSector($sector->key, $fechaBotFormat);
        }
        return $total;
    }

    /**
     * Capacidad total del restaurante (para "Sin preferencia"):
     * suma de cupos de sectores vs personas en la ventana ±1h.
     */
    public static function tieneCapacidadTotal(
        string $fechaBotFormat,
        int $personasNuevas,
        ?string $hora = null,
    ): bool {
        $limite = self::limiteTotalRestaurante($fechaBotFormat);
        if ($limite <= 0) return true;

        $usadas = self::personasEnRestaurante($fechaBotFormat, $hora);
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
     * Verifica si un sector superó el umbral de alerta en la ventana de $hora
     * y emite una PanelNotification si no existe ya una para ese sector+fecha+ventana.
     */
    public static function checkAlertaOcupacion(
        string $sectorKey,
        string $fechaBotFormat,
        ?string $hora = null,
    ): void {
        $config = RestaurantConfig::get();
        $limite = self::limiteEfectivoParaSector($sectorKey, $fechaBotFormat);
        if ($limite <= 0) return;

        $alertaPct = $config->sector_alerta_pct ?? 70;
        $umbral    = (int) floor($limite * $alertaPct / 100);
        $usadas    = self::personasEnSector($sectorKey, $fechaBotFormat, $hora);

        if ($usadas < $umbral) return;

        $horaNorm = self::normalizeHora($hora) ?? '';

        // No crear notificación duplicada para el mismo sector + fecha + ventana
        $yaExiste = PanelNotification::where('tipo', 'sector_alerta')
            ->where('leida', false)
            ->whereRaw("json_extract(payload, '$.sector_key') = ?", [$sectorKey])
            ->whereRaw("json_extract(payload, '$.fecha') = ?", [$fechaBotFormat])
            ->whereRaw("COALESCE(json_extract(payload, '$.hora'), '') = ?", [$horaNorm])
            ->exists();

        if ($yaExiste) return;

        $sectorLabel = RestaurantSector::where('key', $sectorKey)->value('label') ?? $sectorKey;
        $horarioTxt  = $horaNorm !== ''
            ? " alrededor de las *{$horaNorm}*"
            : '';

        PanelNotification::create([
            'tipo'    => 'sector_alerta',
            'payload' => [
                'mensaje'       => "Alcanzamos el {$alertaPct}% de la capacidad en *{$sectorLabel}* para el *{$fechaBotFormat}*{$horarioTxt}. ¿Querés que informemos a quienes reservan que no hay más cupo?",
                'sector_key'    => $sectorKey,
                'sector_label'  => $sectorLabel,
                'fecha'         => $fechaBotFormat,
                'hora'          => $horaNorm !== '' ? $horaNorm : null,
            ],
            'leida' => false,
        ]);
    }

    /**
     * Construye el texto del mensaje de selección de sector (MSG_RES_04),
     * marcando con "(sin capacidad)" los sectores llenos o cerrados
     * para la ventana horaria de la reserva.
     */
    public static function buildSectorMessage(string $fecha, int $personas, ?string $hora = null): string
    {
        $sectores = RestaurantSector::activos();
        $lines    = [];

        foreach ($sectores as $i => $sector) {
            $letra = chr(65 + $i); // A, B, C...
            if ($sector->requiere_capacidad) {
                $disponible = self::tieneCapacidad($sector->key, $fecha, $personas, $hora);
                $suffix     = $disponible ? '' : ' _(sin capacidad)_';
                // Si al armar la lista ya estamos sobre el umbral, avisar al panel
                // (cubre sobrecarga manual / override de cupo sin pasar por una confirmación).
                if (!$disponible) {
                    self::checkAlertaOcupacion($sector->key, $fecha, $hora);
                }
            } else {
                // "Sin preferencia": cupo = suma de todos los sectores en la ventana.
                $disponible = self::tieneCapacidadTotal($fecha, $personas, $hora);
                $suffix     = $disponible ? '' : ' _(sin capacidad)_';
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

    /**
     * Normaliza un label de horario a "HH:MM" (o null si no se puede parsear).
     * Acepta "13:00", "13.15", "Turno noche 1: 20 hs", "11.45 hs", etc.
     */
    public static function normalizeHora(?string $hora): ?string
    {
        $min = self::parseHoraToMinutes($hora);
        if ($min === null) return null;
        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }

    /**
     * Convierte un string de hora a minutos desde medianoche.
     */
    public static function parseHoraToMinutes(?string $hora): ?int
    {
        if ($hora === null || trim($hora) === '') return null;

        $h = str_replace(',', '.', trim($hora));

        // "13:15" / "13.15" / embebido en "Turno … 11.45 hs"
        if (preg_match('/\b(\d{1,2})[:.](\d{2})\b/', $h, $m)) {
            $hh = (int) $m[1];
            $mm = (int) $m[2];
            if ($hh > 23 || $mm > 59) return null;
            return $hh * 60 + $mm;
        }

        // "20 hs" / "20hs"
        if (preg_match('/\b(\d{1,2})\s*hs\b/i', $h, $m)) {
            $hh = (int) $m[1];
            if ($hh > 23) return null;
            return $hh * 60;
        }

        // Solo "13"
        if (preg_match('/^(\d{1,2})$/', trim($hora), $m)) {
            $hh = (int) $m[1];
            if ($hh > 23) return null;
            return $hh * 60;
        }

        return null;
    }
}
