<?php

namespace App\Services;

use App\Models\BotMessageOption;
use App\Models\Feriado;
use App\Models\Reserva;
use App\Models\RestaurantCapacityOverride;
use App\Models\RestaurantConfig;
use App\Models\RestaurantSector;
use Carbon\Carbon;

/**
 * Disponibilidad del restaurante por turno (usada por el WhatsApp Flow).
 *
 * El cupo se controla por ocupación simultánea: un turno con hora de fin
 * ("12 hs (hasta las 16 hs)") ocupa la mesa durante todo ese rango, así que
 * descuenta del cupo de los turnos que empiezan dentro de él (y viceversa).
 * Un turno sin hora de fin solo choca con reservas que están activas en su hora de inicio.
 */
class RestaurantTurnos
{
    public const SIN_PREFERENCIA = 'sin_preferencia';

    private const ESTADOS_ACTIVOS = ['CONFIRMADA', 'PENDIENTE_CONFIRMACION'];

    public function __construct(private readonly Carbon $ahora) {}

    public static function now(): self
    {
        return new self(Carbon::now());
    }

    // No usa Feriado::esFeriado(): compara el cast de fecha ("Y-m-d 00:00:00") contra "Y-m-d" y nunca da true.
    public static function esFinde(Carbon $fecha): bool
    {
        return $fecha->isWeekend() || Feriado::whereDate('fecha', $fecha->toDateString())->exists();
    }

    /**
     * Mismo criterio que RestaurantCapacity::estaCerrado() (el cierre vale para el día en que se
     * cerró y se reabre solo al día siguiente), pero comparando por día: estaCerrado() arma la fecha
     * con la hora actual y por eso nunca considera cerrado un sector el mismo día del cierre.
     */
    private function sectorCerrado(string $sectorKey, Carbon $fecha): bool
    {
        if (!RestaurantCapacity::estaCerrado($sectorKey)) {
            return false;
        }

        $fechaCierre = RestaurantConfig::get()->sectores_cerrado_fecha;

        return $fechaCierre === null || $fecha->copy()->startOfDay()->lte(Carbon::parse($fechaCierre)->startOfDay());
    }

    /**
     * Motivo por el que no se puede reservar esa fecha, o null si se puede.
     *
     * @return 'pasada'|'fuera_de_rango'|'finde_orden_llegada'|null
     */
    public function motivoSinReserva(Carbon $fecha, int $maxDias): ?string
    {
        $dia = $fecha->copy()->startOfDay();
        $hoy = $this->ahora->copy()->startOfDay();

        if ($dia->lt($hoy)) return 'pasada';
        if ($dia->gt($hoy->copy()->addDays($maxDias))) return 'fuera_de_rango';

        if (self::esFinde($dia) && $dia->isSameDay($hoy) && $this->minutosAhora() >= 11 * 60) {
            return 'finde_orden_llegada';
        }

        return null;
    }

    /**
     * Turnos configurados para ese día: los de fin de semana/feriado salen de
     * MSG_RES_FINDE_TURNOS y los de semana de las opciones RES_HORA_RESTAURANTE del panel.
     *
     * @return Turno[]
     */
    public function turnosDelDia(Carbon $fecha): array
    {
        $labels = self::esFinde($fecha)
            ? array_values(array_diff_key(BotMessages::parseOptions('MSG_RES_FINDE_TURNOS'), ['0' => true]))
            : BotMessageOption::activos('RES_HORA_RESTAURANTE')->pluck('label')->all();

        $turnos = [];
        foreach ($labels as $label) {
            $turno = Turno::fromLabel($label);
            if ($turno && !isset($turnos[$turno->hora])) {
                $turnos[$turno->hora] = $turno;
            }
        }

        return array_values($turnos);
    }

    /**
     * Turnos que todavía tienen lugar para $personas, cada uno con sus sectores disponibles.
     *
     * @return array<int, array{turno: Turno, sectores: array<int, array{id: string, title: string, sector_key: ?string}>}>
     */
    public function disponibilidad(Carbon $fecha, int $personas): array
    {
        $ocupacion = $this->ocupacionDelDia($fecha);
        $esHoy     = $fecha->isSameDay($this->ahora);
        $out       = [];

        foreach ($this->turnosDelDia($fecha) as $turno) {
            if ($esHoy && $turno->desde <= $this->minutosAhora()) {
                continue;
            }
            $sectores = $this->sectoresDisponibles($fecha, $turno, $personas, $ocupacion);
            if ($sectores !== []) {
                $out[] = ['turno' => $turno, 'sectores' => $sectores];
            }
        }

        return $out;
    }

    /** @return array<int, array{id: string, title: string, sector_key: ?string}> */
    public function sectoresDisponibles(Carbon $fecha, Turno $turno, int $personas, ?array $ocupacion = null): array
    {
        $ocupacion ??= $this->ocupacionDelDia($fecha);
        $config      = RestaurantConfig::get();
        $override    = RestaurantCapacityOverride::forDate($fecha);

        $out            = [];
        $sinPreferencia = null;
        $capacidadTotal = 0;

        foreach (RestaurantSector::activos() as $sector) {
            if (!$sector->requiere_capacidad) {
                $sinPreferencia ??= $sector;
                continue;
            }
            if ($this->sectorCerrado($sector->key, $fecha)) {
                continue;
            }

            $capacidadTotal += (int) ($config->{"{$sector->key}_capacidad"} ?? 0);

            $limite = $override?->limiteParaSector($sector->key) ?? $config->limiteParaSector($sector->key);
            $pico   = $this->pico($ocupacion, $turno, fn (array $r) => $r['sector_key'] === $sector->key);

            // Igual que RestaurantCapacity::tieneCapacidad(): límite <= 0 significa sin tope.
            if ($limite <= 0 || $pico + $personas <= $limite) {
                $out[] = ['id' => $sector->key, 'title' => $sector->label, 'sector_key' => $sector->key];
            }
        }

        // "Sin preferencia" se ofrece solo si queda algún sector con lugar y el total del
        // turno (incluidas otras reservas sin preferencia) entra en la capacidad máxima del restaurante.
        if ($sinPreferencia && $out !== []) {
            $picoTotal = $this->pico($ocupacion, $turno, fn () => true);
            if ($picoTotal + $personas <= $capacidadTotal) {
                $out[] = ['id' => self::SIN_PREFERENCIA, 'title' => $sinPreferencia->label, 'sector_key' => null];
            }
        }

        return $out;
    }

    public function buscarTurno(Carbon $fecha, string $hora): ?Turno
    {
        foreach ($this->turnosDelDia($fecha) as $turno) {
            if ($turno->hora === $hora) return $turno;
        }

        return null;
    }

    /**
     * Reservas activas del día como intervalos. La duración de reservas viejas se deduce
     * del turno configurado que empieza a esa hora (si no hay, se toman como puntuales).
     *
     * @return array<int, array{desde: int, hasta: ?int, personas: int, sector_key: ?string}>
     */
    public function ocupacionDelDia(Carbon $fecha): array
    {
        $porHora = [];
        foreach ($this->turnosDelDia($fecha) as $turno) {
            $porHora[$turno->desde] = $turno;
        }

        $out = [];
        Reserva::where('rama_servicio', 'RESTAURANTE')
            ->whereIn('estado_reserva', self::ESTADOS_ACTIVOS)
            ->whereRaw("json_extract(datos, '$.fecha') = ?", [$fecha->format('d/m/y')])
            ->each(function (Reserva $r) use (&$out, $porHora) {
                $desde = Turno::minutos((string) ($r->datos['hora'] ?? ''));
                if ($desde === null) return;

                $out[] = [
                    'desde'      => $desde,
                    'hasta'      => ($porHora[$desde] ?? null)?->hasta,
                    'personas'   => RestaurantCapacity::extractPersonas((string) ($r->datos['numero_personas'] ?? '')),
                    'sector_key' => $r->datos['sector_key'] ?? null,
                ];
            });

        return $out;
    }

    /** Máxima cantidad de personas presentes a la vez durante el turno, entre las reservas que pasan el filtro. */
    private function pico(array $ocupacion, Turno $turno, callable $filtro): int
    {
        $reservas = array_values(array_filter($ocupacion, $filtro));

        $instantes = [$turno->desde];
        if ($turno->hasta !== null) {
            foreach ($reservas as $r) {
                if ($r['desde'] > $turno->desde && $r['desde'] < $turno->hasta) {
                    $instantes[] = $r['desde'];
                }
            }
        }

        $max = 0;
        foreach ($instantes as $t) {
            $suma = 0;
            foreach ($reservas as $r) {
                $activa = $r['hasta'] === null
                    ? $r['desde'] === $t
                    : $r['desde'] <= $t && $t < $r['hasta'];
                if ($activa) $suma += $r['personas'];
            }
            $max = max($max, $suma);
        }

        return $max;
    }

    private function minutosAhora(): int
    {
        return $this->ahora->hour * 60 + $this->ahora->minute;
    }
}
