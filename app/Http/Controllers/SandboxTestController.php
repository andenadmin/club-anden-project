<?php

namespace App\Http\Controllers;

use App\Models\BotSession;
use App\Models\Cliente;
use App\Models\ConversationMessage;
use App\Models\Reserva;
use App\Models\RestaurantConfig;
use App\Models\RestaurantSector;
use App\Services\RestaurantCapacity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SandboxTestController extends Controller
{
    private const TEST_PREFIX = '549118888';

    public function index(): Response
    {
        $config   = RestaurantConfig::get();
        $sectores = RestaurantSector::activos();

        return Inertia::render('sandbox-test', [
            'sectores' => $sectores->map(fn ($s) => [
                'key'       => $s->key,
                'label'     => $s->label,
                'capacidad' => $config->limiteParaSector($s->key),
            ])->values(),
        ]);
    }

    public function run(Request $request, string $scenario): JsonResponse
    {
        return match ($scenario) {
            'restaurante-confirmada' => $this->scenarioRestauranteConfirmada($request),
            'restaurante-pendiente'  => $this->scenarioRestaurantePendiente($request),
            'escalada'               => $this->scenarioEscalada($request),
            'sector-alerta'          => $this->scenarioSectorAlerta($request),
            'multi-dias'             => $this->scenarioMultiDias($request),
            'clear'                  => $this->clearAll(),
            default                  => response()->json(['error' => 'Escenario desconocido'], 422),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scenarios
    // ─────────────────────────────────────────────────────────────────────────

    private function scenarioRestauranteConfirmada(Request $request): JsonResponse
    {
        $numero    = $this->nextTestNumber();
        $nombre    = 'Test ' . substr($numero, -4);
        $fecha     = Carbon::today()->addDays(3)->format('d/m/y');
        $sectorKey = $request->input('sector', 'salon');
        $sectorLbl = RestaurantSector::where('key', $sectorKey)->value('label') ?? $sectorKey;

        $cliente = $this->makeCliente($numero, $nombre);

        $reserva = Reserva::create([
            'id_cliente'     => $cliente->id,
            'rama_servicio'  => 'RESTAURANTE',
            'estado_reserva' => 'CONFIRMADA',
            'tiene_extras'   => false,
            'datos'          => [
                'fecha'              => $fecha,
                'hora'               => '20:00',
                'numero_personas'    => '3 a 4 personas',
                'sector'             => $sectorLbl,
                'sector_key'         => $sectorKey,
                'nombre_responsable' => $nombre,
                'mail_contacto'      => null,
                'extras_texto'       => null,
                'tiene_extras'       => false,
            ],
        ]);

        $session = $this->makeSession($numero, [
            'id_cliente'      => $cliente->id,
            'estado_actual'   => 'COMPLETADO',
            'rama_activa'     => 'RESTAURANTE',
            'etiqueta'        => 'confirmada',
            'datos_parciales' => ['reserva_id' => $reserva->id],
            'last_message_at' => now(),
        ]);

        $this->addFakeRestauranteConversation($session, $nombre, $fecha, $sectorLbl, now()->subMinutes(8));

        return response()->json([
            'ok'    => true,
            'lines' => [
                "Numero: {$numero}",
                "Reserva ID: {$reserva->id} — CONFIRMADA en {$sectorLbl} para {$fecha}",
                "Conversacion creada con etiqueta 'confirmada'",
                "Aparece en Inbox y en Reservas",
            ],
        ]);
    }

    private function scenarioRestaurantePendiente(Request $request): JsonResponse
    {
        $numero    = $this->nextTestNumber();
        $nombre    = 'Test ' . substr($numero, -4);
        $fecha     = Carbon::today()->addDays(7)->format('d/m/y');
        $sectorKey = $request->input('sector', 'galeria');
        $sectorLbl = RestaurantSector::where('key', $sectorKey)->value('label') ?? $sectorKey;

        $cliente = $this->makeCliente($numero, $nombre);

        $reserva = Reserva::create([
            'id_cliente'     => $cliente->id,
            'rama_servicio'  => 'RESTAURANTE',
            'estado_reserva' => 'PENDIENTE_CONFIRMACION',
            'tiene_extras'   => false,
            'datos'          => [
                'fecha'              => $fecha,
                'hora'               => '13:30',
                'numero_personas'    => '1 a 2 personas',
                'sector'             => $sectorLbl,
                'sector_key'         => $sectorKey,
                'nombre_responsable' => $nombre,
                'mail_contacto'      => null,
                'extras_texto'       => null,
                'tiene_extras'       => false,
            ],
        ]);

        $session = $this->makeSession($numero, [
            'id_cliente'      => $cliente->id,
            'estado_actual'   => 'COMPLETADO',
            'rama_activa'     => 'RESTAURANTE',
            'etiqueta'        => 'abierta',
            'datos_parciales' => ['reserva_id' => $reserva->id],
            'last_message_at' => now(),
        ]);

        $this->addFakeRestauranteConversation($session, $nombre, $fecha, $sectorLbl, now()->subMinutes(20));

        return response()->json([
            'ok'    => true,
            'lines' => [
                "Numero: {$numero}",
                "Reserva ID: {$reserva->id} — PENDIENTE en {$sectorLbl} para {$fecha}",
                "Conversacion creada con etiqueta 'abierta'",
                "Aparece en Inbox (borde ambar) y en Reservas",
            ],
        ]);
    }

    private function scenarioEscalada(Request $request): JsonResponse
    {
        $numero = $this->nextTestNumber();
        $nombre = 'Test ' . substr($numero, -4);

        $cliente = $this->makeCliente($numero, $nombre);

        $session = $this->makeSession($numero, [
            'id_cliente'        => $cliente->id,
            'estado_actual'     => 'PAUSADO',
            'estado_previo_pausa' => 'MENU_PRINCIPAL',
            'rama_activa'       => null,
            'etiqueta'          => 'abierta',
            'motivo_pausa'      => 'El usuario solicitó hablar con un asesor',
            'timestamp_pausa'   => now()->subMinutes(5),
            'unread_count'      => 1,
            'last_message_at'   => now()->subMinutes(5),
        ]);

        $t = now()->subMinutes(7);
        $this->addMessages($session, [
            ['direction' => 'inbound',  'sender' => 'user', 'body' => 'Hola',                    'at' => $t->copy()],
            ['direction' => 'outbound', 'sender' => 'bot',  'body' => "Hola *{$nombre}*! ¿En qué te puedo ayudar?\n\n*A.* Restaurante\n*B.* Eventos\n*0.* Hablar con asesor", 'at' => $t->copy()->addSeconds(2)],
            ['direction' => 'inbound',  'sender' => 'user', 'body' => '0',                       'at' => $t->copy()->addMinutes(2)],
            ['direction' => 'outbound', 'sender' => 'bot',  'body' => 'Entendido, te paso con un asesor en breve.', 'at' => $t->copy()->addMinutes(2)->addSeconds(2)],
        ]);

        return response()->json([
            'ok'    => true,
            'lines' => [
                "Numero: {$numero}",
                "Sesion en estado PAUSADO (solicitó asesor)",
                "Aparece en Inbox con borde ambar y notificacion de escalado",
                "La campana deberia sonar si tenes notificaciones activas",
            ],
        ]);
    }

    private function scenarioSectorAlerta(Request $request): JsonResponse
    {
        $sectorKey = $request->input('sector', 'salon');
        $fecha     = Carbon::today()->addDays(2)->format('d/m/y');
        $config    = RestaurantConfig::get();
        $limite    = $config->limiteParaSector($sectorKey);
        $sectorLbl = RestaurantSector::where('key', $sectorKey)->value('label') ?? $sectorKey;

        if ($limite <= 0) {
            return response()->json(['error' => "El sector '{$sectorLbl}' no tiene capacidad configurada"], 422);
        }

        $alertaPct   = $config->sector_alerta_pct ?? 70;
        $umbral      = (int) floor($limite * $alertaPct / 100);
        $yaOcupadas  = RestaurantCapacity::personasEnSector($sectorKey, $fecha);
        $necesarias  = max(0, $umbral - $yaOcupadas + 1);
        $creadas     = 0;

        // Crear reservas en lotes de "3 a 4 personas" (= 4 personas cada una)
        while ($necesarias > 0) {
            $personas     = min(4, $necesarias);
            $personasStr  = $personas <= 2 ? '1 a 2 personas' : '3 a 4 personas';
            $numero       = $this->nextTestNumber();
            $nombre       = 'Test ' . substr($numero, -4);

            $cliente = $this->makeCliente($numero, $nombre);

            Reserva::create([
                'id_cliente'     => $cliente->id,
                'rama_servicio'  => 'RESTAURANTE',
                'estado_reserva' => 'CONFIRMADA',
                'tiene_extras'   => false,
                'datos'          => [
                    'fecha'              => $fecha,
                    'hora'               => '21:00',
                    'numero_personas'    => $personasStr,
                    'sector'             => $sectorLbl,
                    'sector_key'         => $sectorKey,
                    'nombre_responsable' => $nombre,
                    'mail_contacto'      => null,
                    'extras_texto'       => null,
                    'tiene_extras'       => false,
                ],
            ]);

            $necesarias -= $personas;
            $creadas++;
        }

        // Disparar la alerta
        RestaurantCapacity::checkAlertaOcupacion($sectorKey, $fecha);

        $totalAhora = RestaurantCapacity::personasEnSector($sectorKey, $fecha);

        return response()->json([
            'ok'    => true,
            'lines' => [
                "Sector: {$sectorLbl} — fecha: {$fecha}",
                "Capacidad: {$totalAhora}/{$limite} personas ({$alertaPct}% umbral = {$umbral})",
                "Reservas de prueba creadas: {$creadas}",
                "Alerta sector_alerta disparada en PanelNotifications",
                "Deberia aparecer el banner ambar en el Inbox",
            ],
        ]);
    }

    private function scenarioMultiDias(Request $request): JsonResponse
    {
        $numero = $this->nextTestNumber();
        $nombre = 'Test ' . substr($numero, -4);

        $cliente = $this->makeCliente($numero, $nombre);

        $session = $this->makeSession($numero, [
            'id_cliente'      => $cliente->id,
            'estado_actual'   => 'COMPLETADO',
            'rama_activa'     => 'RESTAURANTE',
            'etiqueta'        => 'finalizada',
            'last_message_at' => now(),
        ]);

        // Mensajes distribuidos en 4 dias distintos
        $dias = [
            Carbon::today()->subDays(6)->setTime(18, 30),
            Carbon::today()->subDays(4)->setTime(9, 15),
            Carbon::today()->subDays(2)->setTime(20, 0),
            Carbon::today()->setTime(10, 45),
        ];

        $conversaciones = [
            [
                ['direction' => 'inbound',  'sender' => 'user', 'body' => 'Hola quiero reservar para el sabado', 'at' => $dias[0]->copy()],
                ['direction' => 'outbound', 'sender' => 'bot',  'body' => "Hola *{$nombre}*! Con gusto te ayudo. ¿Qué servicio necesitás?\n\n*A.* Restaurante\n*B.* Eventos", 'at' => $dias[0]->copy()->addSeconds(3)],
                ['direction' => 'inbound',  'sender' => 'user', 'body' => 'A', 'at' => $dias[0]->copy()->addMinutes(2)],
                ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¿Para qué fecha?', 'at' => $dias[0]->copy()->addMinutes(2)->addSeconds(2)],
            ],
            [
                ['direction' => 'inbound',  'sender' => 'user', 'body' => 'Hola seguimos?', 'at' => $dias[1]->copy()],
                ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¡Hola! Claro, seguimos. ¿Para qué fecha querés reservar?', 'at' => $dias[1]->copy()->addSeconds(5)],
                ['direction' => 'inbound',  'sender' => 'user', 'body' => Carbon::today()->addDays(5)->format('d/m/y'), 'at' => $dias[1]->copy()->addMinutes(1)],
                ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¿A qué hora preferís?', 'at' => $dias[1]->copy()->addMinutes(1)->addSeconds(3)],
            ],
            [
                ['direction' => 'inbound',  'sender' => 'user', 'body' => '21:00', 'at' => $dias[2]->copy()],
                ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¿Para cuántas personas?', 'at' => $dias[2]->copy()->addSeconds(3)],
                ['direction' => 'inbound',  'sender' => 'user', 'body' => '5 a 6 personas', 'at' => $dias[2]->copy()->addMinutes(3)],
                ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¿Algún pedido especial o todo bien así?', 'at' => $dias[2]->copy()->addMinutes(3)->addSeconds(2)],
                ['direction' => 'inbound',  'sender' => 'user', 'body' => 'Todo bien', 'at' => $dias[2]->copy()->addMinutes(5)],
            ],
            [
                ['direction' => 'outbound', 'sender' => 'bot',  'body' => "¡Reserva confirmada! 🎉\n\nTe esperamos el " . Carbon::today()->addDays(5)->format('d/m/y') . " a las 21:00.", 'at' => $dias[3]->copy()],
                ['direction' => 'inbound',  'sender' => 'user', 'body' => 'Gracias!', 'at' => $dias[3]->copy()->addMinutes(1)],
                ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¡Con gusto! Hasta pronto 👋', 'at' => $dias[3]->copy()->addMinutes(1)->addSeconds(5)],
            ],
        ];

        foreach ($conversaciones as $grupo) {
            $this->addMessages($session, $grupo);
        }

        return response()->json([
            'ok'    => true,
            'lines' => [
                "Numero: {$numero}",
                "Conversacion con mensajes en 4 dias distintos",
                "Dias: hace 6 dias, hace 4 dias, hace 2 dias y hoy",
                "Abri la conversacion en Inbox para ver los separadores de dia",
                "Etiqueta: 'finalizada' (gris)",
            ],
        ]);
    }

    private function clearAll(): JsonResponse
    {
        $sessions = BotSession::where('numero_contacto', 'like', self::TEST_PREFIX . '%')->get();

        $count = [
            'sessions'  => 0,
            'messages'  => 0,
            'reservas'  => 0,
            'clientes'  => 0,
        ];

        foreach ($sessions as $session) {
            $count['messages'] += $session->messages()->count();
            $session->messages()->delete();
            $count['sessions']++;

            if ($session->id_cliente) {
                $r = Reserva::where('id_cliente', $session->id_cliente)->count();
                $count['reservas'] += $r;
                Reserva::where('id_cliente', $session->id_cliente)->delete();
                Cliente::find($session->id_cliente)?->delete();
                $count['clientes']++;
            }

            $session->delete();
        }

        // También clientes sin sesión creados por sector-alerta
        $clientes = Cliente::where('numero_contacto', 'like', self::TEST_PREFIX . '%')->get();
        foreach ($clientes as $c) {
            $r = Reserva::where('id_cliente', $c->id)->count();
            $count['reservas'] += $r;
            Reserva::where('id_cliente', $c->id)->delete();
            $c->delete();
            $count['clientes']++;
        }

        return response()->json([
            'ok'    => true,
            'lines' => [
                "Datos de prueba eliminados:",
                "  Sesiones: {$count['sessions']}",
                "  Mensajes: {$count['messages']}",
                "  Reservas: {$count['reservas']}",
                "  Clientes: {$count['clientes']}",
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function nextTestNumber(): string
    {
        $last = BotSession::where('numero_contacto', 'like', self::TEST_PREFIX . '%')
            ->orderByDesc('numero_contacto')
            ->value('numero_contacto');

        if (!$last) {
            $last = Cliente::where('numero_contacto', 'like', self::TEST_PREFIX . '%')
                ->orderByDesc('numero_contacto')
                ->value('numero_contacto');
        }

        $next = $last ? ((int) substr($last, strlen(self::TEST_PREFIX)) + 1) : 1;

        return self::TEST_PREFIX . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function makeCliente(string $numero, string $nombre): Cliente
    {
        return Cliente::firstOrCreate(
            ['numero_contacto' => $numero],
            ['nombre_cliente'  => $nombre],
        );
    }

    private function makeSession(string $numero, array $attrs): BotSession
    {
        return BotSession::create(array_merge([
            'numero_contacto' => $numero,
            'estado_actual'   => 'COMPLETADO',
        ], $attrs));
    }

    private function addMessages(BotSession $session, array $messages): void
    {
        foreach ($messages as $m) {
            ConversationMessage::create([
                'bot_session_id' => $session->id,
                'direction'      => $m['direction'],
                'sender'         => $m['sender'],
                'body'           => $m['body'],
                'created_at'     => $m['at'],
                'updated_at'     => $m['at'],
            ]);
        }
    }

    private function addFakeRestauranteConversation(BotSession $session, string $nombre, string $fecha, string $sectorLbl, Carbon $startAt): void
    {
        $t = $startAt;
        $this->addMessages($session, [
            ['direction' => 'inbound',  'sender' => 'user', 'body' => 'Hola',                                       'at' => $t->copy()],
            ['direction' => 'outbound', 'sender' => 'bot',  'body' => "Hola *{$nombre}*! ¿En qué te puedo ayudar?\n\n*A.* Restaurante\n*B.* Eventos\n*0.* Asesor", 'at' => $t->copy()->addSeconds(2)],
            ['direction' => 'inbound',  'sender' => 'user', 'body' => 'A',                                          'at' => $t->copy()->addSeconds(20)],
            ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¿Para qué fecha querés reservar?',           'at' => $t->copy()->addSeconds(22)],
            ['direction' => 'inbound',  'sender' => 'user', 'body' => $fecha,                                       'at' => $t->copy()->addMinutes(2)],
            ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¿A qué hora?',                              'at' => $t->copy()->addMinutes(2)->addSeconds(2)],
            ['direction' => 'inbound',  'sender' => 'user', 'body' => '20:00',                                      'at' => $t->copy()->addMinutes(3)],
            ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¿Para cuántas personas?',                    'at' => $t->copy()->addMinutes(3)->addSeconds(2)],
            ['direction' => 'inbound',  'sender' => 'user', 'body' => '3 a 4 personas',                             'at' => $t->copy()->addMinutes(4)],
            ['direction' => 'outbound', 'sender' => 'bot',  'body' => "¿Tenés preferencia de sector?\n\n*A.* {$sectorLbl}\n*B.* Otro", 'at' => $t->copy()->addMinutes(4)->addSeconds(2)],
            ['direction' => 'inbound',  'sender' => 'user', 'body' => 'A',                                          'at' => $t->copy()->addMinutes(5)],
            ['direction' => 'outbound', 'sender' => 'bot',  'body' => '¡Reserva confirmada! 🎉',                    'at' => $t->copy()->addMinutes(6)],
        ]);
    }
}
