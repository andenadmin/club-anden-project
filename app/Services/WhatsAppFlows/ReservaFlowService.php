<?php

namespace App\Services\WhatsAppFlows;

use App\Models\Cliente;
use App\Models\WhatsAppFlowToken;
use App\Services\RestaurantTurnos;
use App\Services\Turno;
use App\Services\WhatsAppFlows\Exceptions\InvalidFlowTokenException;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Lógica del endpoint del Flow de reserva de restaurante. Recibe el payload ya descifrado
 * y devuelve la respuesta (sin cifrar) que espera WhatsApp.
 */
class ReservaFlowService
{
    public const PANTALLA_FECHA = 'FECHA_PERSONAS';
    public const PANTALLA_DATOS = 'DATOS';
    public const PANTALLA_GRUPO = 'GRUPO';
    public const PERSONAS_GRUPO = '15+';

    private const MSG = [
        'fecha_invalida'      => 'Elegí una fecha válida.',
        'pasada'              => 'Esa fecha ya pasó. Elegí otra.',
        'fuera_de_rango'      => 'Podés reservar hasta 7 días antes. Elegí otra fecha.',
        'finde_orden_llegada' => 'Hoy ya no tomamos reservas: sábados, domingos y feriados, después de las 11 se atiende por orden de llegada. ¡Te esperamos! 🌿',
        'personas_invalidas'  => 'Elegí cuántas personas van a ser.',
        'sin_lugar_dia'       => 'No nos quedan lugares para %s ese día. Probá con otra fecha 🙏',
        'turno_lleno'         => 'Ese horario se acaba de llenar. Elegí otro 🙏',
        'sector_lleno'        => 'Ese sector se acaba de llenar. Elegí otro sector u horario 🙏',
        'nombre'              => 'Completá a nombre de quién es la reserva.',
        'tyc'                 => 'Para reservar tenés que aceptar los Términos y condiciones.',
        'grupo_cantidad'      => 'Para menos de 15 personas volvé atrás y elegí la cantidad en la pantalla anterior.',
        'demanda'             => 'Estamos con mucha demanda. Tocá de nuevo en unos segundos.',
        'generico'            => 'Algo salió mal. Volvé a intentar.',
    ];

    public function __construct(private readonly ReservaRestauranteCreator $creator) {}

    /** @throws InvalidFlowTokenException */
    public function handle(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        $data   = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ($action === 'ping') {
            return ['data' => ['status' => 'active']];
        }

        if (isset($data['error'])) {
            Log::warning('[FLOW][RESERVA] Error reportado por WhatsApp', [
                'flow_token' => $payload['flow_token'] ?? null,
                'error'      => $data['error'],
                'mensaje'    => $data['error_message'] ?? null,
            ]);

            return ['data' => ['acknowledged' => true]];
        }

        $token = WhatsAppFlowToken::vigente($payload['flow_token'] ?? null, WhatsAppFlowToken::FLOW_RESERVA);
        if (!$token || $token->completed_at) {
            throw new InvalidFlowTokenException('flow_token inválido, vencido o ya usado.');
        }

        $turnos = RestaurantTurnos::now();

        if ($action !== 'data_exchange') {
            return $this->pantallaFecha(); // INIT y BACK
        }

        return match ($data['accion'] ?? null) {
            'buscar'    => $this->buscar($turnos, $token, $data),
            'turno'     => $this->cambiarTurno($turnos, $data),
            'confirmar' => $this->confirmar($turnos, $token, $data),
            'grupo'     => $this->grupo($turnos, $token, $data),
            default     => $this->pantallaFecha(self::MSG['generico']),
        };
    }

    // ─── Acciones ────────────────────────────────────────────────────────────

    private function buscar(RestaurantTurnos $turnos, WhatsAppFlowToken $token, array $data): array
    {
        $fecha = $this->parseFecha($data['fecha'] ?? null);
        if (!$fecha) return $this->pantallaFecha(self::MSG['fecha_invalida']);

        if ($motivo = $turnos->motivoSinReserva($fecha, $this->maxDias())) {
            return $this->pantallaFecha(self::MSG[$motivo]);
        }

        $nombre = $this->nombreRegistrado($token);

        if (($data['personas'] ?? null) === self::PERSONAS_GRUPO) {
            return $this->pantallaGrupo($fecha, $nombre);
        }

        $personas = $this->parsePersonas($data['personas'] ?? null);
        if (!$personas) return $this->pantallaFecha(self::MSG['personas_invalidas']);

        $disp = $turnos->disponibilidad($fecha, $personas);
        if ($disp === []) {
            return $this->pantallaFecha(sprintf(self::MSG['sin_lugar_dia'], $this->textoPersonas($personas)));
        }

        return $this->pantallaDatos($fecha, $personas, $disp, null, $nombre, '');
    }

    private function cambiarTurno(RestaurantTurnos $turnos, array $data): array
    {
        $fecha    = $this->parseFecha($data['fecha'] ?? null);
        $personas = $this->parsePersonas($data['personas'] ?? null);
        if (!$fecha || !$personas) return $this->pantallaFecha(self::MSG['generico']);

        $disp = $turnos->disponibilidad($fecha, $personas);
        if ($disp === []) {
            return $this->pantallaFecha(sprintf(self::MSG['sin_lugar_dia'], $this->textoPersonas($personas)));
        }

        $hora  = (string) ($data['turno'] ?? '');
        $error = $this->entradaTurno($disp, $hora) ? null : self::MSG['turno_lleno'];

        return $this->pantallaDatos($fecha, $personas, $disp, $hora, $this->texto($data['nombre'] ?? ''), $this->texto($data['comentarios'] ?? ''), $error);
    }

    private function confirmar(RestaurantTurnos $turnos, WhatsAppFlowToken $token, array $data): array
    {
        $fecha    = $this->parseFecha($data['fecha'] ?? null);
        $personas = $this->parsePersonas($data['personas'] ?? null);
        if (!$fecha || !$personas) return $this->pantallaFecha(self::MSG['generico']);

        if ($motivo = $turnos->motivoSinReserva($fecha, $this->maxDias())) {
            return $this->pantallaFecha(self::MSG[$motivo]);
        }

        $hora        = (string) ($data['turno'] ?? '');
        $sectorId    = (string) ($data['sector'] ?? '');
        $nombre      = $this->texto($data['nombre'] ?? '', 60);
        $comentarios = $this->texto($data['comentarios'] ?? '', 300);

        $reintentar = fn (array $disp, string $error) => $disp === []
            ? $this->pantallaFecha(sprintf(self::MSG['sin_lugar_dia'], $this->textoPersonas($personas)))
            : $this->pantallaDatos($fecha, $personas, $disp, $hora, $nombre, $comentarios, $error);

        if ($nombre === '' || !$this->aceptaTyc($data['acepta_tyc'] ?? null)) {
            return $reintentar(
                $turnos->disponibilidad($fecha, $personas),
                $nombre === '' ? self::MSG['nombre'] : self::MSG['tyc'],
            );
        }

        try {
            // El lock serializa las confirmaciones del mismo día: la verificación de cupo
            // y la creación de la reserva tienen que ser atómicas entre sí.
            return Cache::lock('reserva-restaurante:' . $fecha->toDateString(), 15)->block(8, function () use (
                $turnos, $token, $fecha, $personas, $hora, $sectorId, $nombre, $comentarios, $reintentar
            ) {
                $disp    = $turnos->disponibilidad($fecha, $personas);
                $entrada = $this->entradaTurno($disp, $hora);
                if (!$entrada) {
                    return $reintentar($disp, self::MSG['turno_lleno']);
                }

                $sector = collect($entrada['sectores'])->firstWhere('id', $sectorId);
                if (!$sector) {
                    return $reintentar($disp, self::MSG['sector_lleno']);
                }

                $session = $token->session;
                $cliente = Cliente::firstOrCreate(['numero_contacto' => $session->numero_contacto]);
                if (!$cliente->nombre_cliente) {
                    $cliente->update(['nombre_cliente' => $nombre]);
                }
                if ($session->id_cliente !== $cliente->id) {
                    $session->mergeEstado(['id_cliente' => $cliente->id]);
                }

                $reserva = $this->creator->crear($session, $cliente, [
                    'fecha'              => $fecha->format('d/m/y'),
                    'hora'               => $entrada['turno']->hora,
                    'numero_personas'    => $this->textoPersonas($personas),
                    'sector'             => $sector['title'],
                    'sector_key'         => $sector['sector_key'],
                    'nombre_responsable' => $nombre,
                    'comentarios'        => $comentarios !== '' ? $comentarios : null,
                    'es_finde'           => RestaurantTurnos::esFinde($fecha),
                    'origen'             => 'whatsapp_flow',
                ]);

                $token->update(['completed_at' => now(), 'resultado' => 'reserva', 'reserva_id' => $reserva->id]);

                return $this->completar($token, ['resultado' => 'reserva', 'reserva_id' => $reserva->id]);
            });
        } catch (LockTimeoutException) {
            return $reintentar($turnos->disponibilidad($fecha, $personas), self::MSG['demanda']);
        }
    }

    private function grupo(RestaurantTurnos $turnos, WhatsAppFlowToken $token, array $data): array
    {
        $fecha = $this->parseFecha($data['fecha'] ?? null);
        if (!$fecha) return $this->pantallaFecha(self::MSG['generico']);

        if ($motivo = $turnos->motivoSinReserva($fecha, $this->maxDias())) {
            return $this->pantallaFecha(self::MSG[$motivo]);
        }

        $nombre   = $this->texto($data['nombre'] ?? '', 60);
        $cantidad = filter_var($data['cantidad'] ?? null, FILTER_VALIDATE_INT);

        $error = match (true) {
            $nombre === ''                              => self::MSG['nombre'],
            $cantidad === false || $cantidad < 15       => self::MSG['grupo_cantidad'],
            !$this->aceptaTyc($data['acepta_tyc'] ?? null) => self::MSG['tyc'],
            default                                     => null,
        };
        if ($error) return $this->pantallaGrupo($fecha, $nombre, $error);

        $session = $token->session;
        $cliente = Cliente::firstOrCreate(['numero_contacto' => $session->numero_contacto]);
        if (!$cliente->nombre_cliente) {
            $cliente->update(['nombre_cliente' => $nombre]);
        }

        $comentarios = $this->texto($data['comentarios'] ?? '', 300);
        $session->mergeEstado([
            'id_cliente'      => $cliente->id,
            'rama_activa'     => 'RESTAURANTE',
            'datos_parciales' => [
                'fecha'              => $fecha->format('d/m/y'),
                'numero_personas'    => "{$cantidad} personas",
                'nombre_responsable' => $nombre,
                'comentarios'        => $comentarios !== '' ? $comentarios : null,
                'origen'             => 'whatsapp_flow',
            ],
        ]);

        $token->update(['completed_at' => now(), 'resultado' => 'grupo']);

        return $this->completar($token, ['resultado' => 'grupo']);
    }

    // ─── Pantallas ───────────────────────────────────────────────────────────

    private function pantallaFecha(?string $error = null): array
    {
        $opciones = [];
        for ($n = 1; $n <= (int) config('whatsapp_flows.reserva.max_personas_flow', 14); $n++) {
            $opciones[] = ['id' => (string) $n, 'title' => $this->textoPersonas($n)];
        }
        $opciones[] = ['id' => self::PERSONAS_GRUPO, 'title' => '15 o más'];

        return [
            'screen' => self::PANTALLA_FECHA,
            'data'   => [
                'min_fecha'         => Carbon::today()->toDateString(),
                'max_fecha'         => Carbon::today()->addDays($this->maxDias())->toDateString(),
                'personas_opciones' => $opciones,
                'hay_error'         => $error !== null,
                'error_msg'         => $error ?? '',
            ],
        ];
    }

    private function pantallaDatos(Carbon $fecha, int $personas, array $disp, ?string $hora, string $nombre, string $comentarios, ?string $error = null): array
    {
        $entrada = ($hora !== null ? $this->entradaTurno($disp, $hora) : null) ?? $disp[0];

        return [
            'screen' => self::PANTALLA_DATOS,
            'data'   => [
                'fecha'       => $fecha->toDateString(),
                'personas'    => (string) $personas,
                'resumen'     => $this->textoFecha($fecha) . ' · ' . $this->textoPersonas($personas),
                'turnos'      => array_map(fn (array $d) => $this->opcionTurno($d['turno']), $disp),
                'turno_sel'   => $entrada['turno']->hora,
                'sectores'    => array_map(fn (array $s) => ['id' => $s['id'], 'title' => $s['title']], $entrada['sectores']),
                'nombre'      => $nombre,
                'comentarios' => $comentarios,
                'hay_error'   => $error !== null,
                'error_msg'   => $error ?? '',
            ],
        ];
    }

    private function pantallaGrupo(Carbon $fecha, string $nombre, ?string $error = null): array
    {
        return [
            'screen' => self::PANTALLA_GRUPO,
            'data'   => [
                'fecha'     => $fecha->toDateString(),
                'resumen'   => $this->textoFecha($fecha) . ' · 15 personas o más',
                'nombre'    => $nombre,
                'hay_error' => $error !== null,
                'error_msg' => $error ?? '',
            ],
        ];
    }

    private function completar(WhatsAppFlowToken $token, array $params): array
    {
        return [
            'screen' => 'SUCCESS',
            'data'   => [
                'extension_message_response' => [
                    'params' => ['flow_token' => $token->token] + $params,
                ],
            ],
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** El título del Dropdown tiene límite corto: va la hora y el texto del panel queda como descripción. */
    private function opcionTurno(Turno $turno): array
    {
        $title = $turno->hora . ' hs';
        if ($turno->hasta !== null) {
            $title = sprintf('%s a %02d:%02d hs', $turno->hora, intdiv($turno->hasta, 60), $turno->hasta % 60);
        }

        return ['id' => $turno->hora, 'title' => $title, 'description' => mb_substr($turno->label, 0, 300)];
    }

    private function entradaTurno(array $disp, string $hora): ?array
    {
        foreach ($disp as $d) {
            if ($d['turno']->hora === $hora) return $d;
        }

        return null;
    }

    private function parseFecha(mixed $valor): ?Carbon
    {
        if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return null;

        try {
            return Carbon::createFromFormat('!Y-m-d', $valor);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parsePersonas(mixed $valor): ?int
    {
        $n = filter_var($valor, FILTER_VALIDATE_INT);
        $max = (int) config('whatsapp_flows.reserva.max_personas_flow', 14);

        return ($n !== false && $n >= 1 && $n <= $max) ? $n : null;
    }

    private function aceptaTyc(mixed $valor): bool
    {
        return $valor === true || $valor === 'true';
    }

    private function texto(mixed $valor, int $max = 300): string
    {
        return is_string($valor) ? mb_substr(trim($valor), 0, $max) : '';
    }

    private function nombreRegistrado(WhatsAppFlowToken $token): string
    {
        return (string) (Cliente::where('numero_contacto', $token->session->numero_contacto)->value('nombre_cliente') ?? '');
    }

    private function textoPersonas(int $n): string
    {
        return $n === 1 ? '1 persona' : "{$n} personas";
    }

    private function textoFecha(Carbon $fecha): string
    {
        return ucfirst($fecha->copy()->locale('es')->isoFormat('dddd D/M'));
    }

    private function maxDias(): int
    {
        return (int) config('whatsapp_flows.reserva.max_dias_anticipacion', 7);
    }
}
