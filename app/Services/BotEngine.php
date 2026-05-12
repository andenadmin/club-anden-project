<?php

namespace App\Services;

use App\Models\BotSession;
use App\Models\Cliente;
use App\Models\ConversationMessage;
use App\Models\CrmCliente;
use App\Models\CostoEvento;
use App\Models\Feriado;
use App\Models\Reserva;
use Carbon\Carbon;

class BotEngine
{
    /**
     * Procesa un mensaje entrante y devuelve las respuestas que el bot quiere mandar.
     * Loguea el inbound automáticamente. El logueo del outbound queda en manos del caller
     * (`BotSimulatorController` lo loguea localmente; `WhatsAppSender` lo loguea con
     * `wa_message_id` después de mandarlo a Meta).
     *
     * @return string[] Las respuestas a enviar al usuario, en orden.
     */
    public function process(string $from, string $text): array
    {
        $text    = trim($text);
        $session = BotSession::firstOrCreate(
            ['numero_contacto' => $from],
            ['estado_actual' => 'INICIO', 'datos_parciales' => [], 'contador_invalidos' => 0]
        );

        $this->logInbound($session, $text);

        return $this->dispatch($session, $text);
    }

    /**
     * Persiste un mensaje saliente y lo asocia a la sesión.
     * Lo usan tanto el simulador (sin `$waMessageId`) como `WhatsAppSender` (con).
     */
    public function logOutbound(BotSession $session, string $body, ?string $waMessageId = null, string $sender = ConversationMessage::SENDER_BOT): ConversationMessage
    {
        $msg = ConversationMessage::create([
            'bot_session_id' => $session->id,
            'direction'      => ConversationMessage::DIRECTION_OUTBOUND,
            'sender'         => $sender,
            'body'           => $body,
            'wa_message_id'  => $waMessageId,
        ]);

        $session->mergeEstado(['last_message_at' => Carbon::now()]);

        return $msg;
    }

    private function dispatch(BotSession $session, string $text): array
    {
        // Estado PAUSADO
        if ($session->estado_actual === 'PAUSADO') {
            if ($session->timestamp_pausa && Carbon::now()->diffInHours($session->timestamp_pausa, false) <= -12) {
                $session->mergeEstado([
                    'estado_actual'    => 'INICIO',
                    'timestamp_pausa'  => null,
                ]);
                return $this->handleInicio($session);
            }
            return [];
        }

        // Trigger global de escalado (prioridad absoluta)
        if ($text === '0' || strtolower($text) === 'atencion') {
            return $this->escalate($session, 'SOLICITUD_CLIENTE');
        }

        // Navegación hacia atrás
        if (in_array(strtolower($text), ['atras', 'atrás', 'volver', 'back'], true)) {
            return $this->handleBack($session);
        }

        return match ($session->estado_actual) {
            'INICIO'             => $this->handleInicio($session),
            'REGISTRO_CLIENTE'   => $this->handleRegistroCliente($session, $text),
            'MENU_PRINCIPAL'     => $this->handleMenuPrincipal($session, $text),
            'RECOLECTANDO_DATOS' => $this->handleRecolectandoDatos($session, $text),
            'CONFIRMACION'       => $this->handleConfirmacion($session, $text),
            'COMPLETADO'         => $this->handleCompletado($session, $text),
            'CAMBIANDO_DATO'     => $this->handleCambiandoDato($session, $text),
            default              => $this->handleInicio($session),
        };
    }

    private function logInbound(BotSession $session, string $body): void
    {
        ConversationMessage::create([
            'bot_session_id' => $session->id,
            'direction'      => ConversationMessage::DIRECTION_INBOUND,
            'sender'         => ConversationMessage::SENDER_USER,
            'body'           => $body,
        ]);

        $attrs = ['last_message_at' => Carbon::now()];
        if ($session->estado_actual === 'PAUSADO') {
            $attrs['unread_count'] = ($session->unread_count ?? 0) + 1;
        }
        $session->mergeEstado($attrs);
    }


    // ─── ESTADOS ──────────────────────────────────────────────────────────────

    private function handleInicio(BotSession $session): array
    {
        $cliente = Cliente::firstOrCreate(['numero_contacto' => $session->numero_contacto]);

        $session->mergeEstado(['id_cliente' => $cliente->id, 'contador_invalidos' => 0]);

        if ($cliente->nombre_cliente) {
            $session->mergeEstado(['estado_actual' => 'MENU_PRINCIPAL']);
            return [BotMessages::render('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $cliente->nombre_cliente])];
        }

        $session->mergeEstado(['estado_actual' => 'REGISTRO_CLIENTE']);
        return [BotMessages::render('MSG_REGISTRO_PEDIR_NOMBRE')];
    }

    private function handleRegistroCliente(BotSession $session, string $text): array
    {
        $nombre  = $text;
        $cliente = Cliente::find($session->id_cliente);
        $cliente?->update(['nombre_cliente' => $nombre]);

        $session->mergeEstado(['estado_actual' => 'MENU_PRINCIPAL', 'contador_invalidos' => 0]);
        return [BotMessages::render('MSG_REGISTRO_BIENVENIDA', ['nombre' => $nombre])];
    }

    private function handleMenuPrincipal(BotSession $session, string $text): array
    {
        $nombre  = Cliente::find($session->id_cliente)?->nombre_cliente ?? 'cliente';
        $opts    = BotMessages::parseOptions('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $nombre]);
        // Excluir opción 0 (escalado global)
        $keys    = array_values(array_filter(array_keys($opts), fn ($k) => $k !== '0'));
        $choice  = strtoupper(trim($text));

        if (!isset($opts[$choice])) {
            return $this->handleInvalid(
                $session,
                fn () => [BotMessages::render('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $nombre])]
            );
        }

        // Posición 0 = deportes, 1 = restaurante, 2 = eventos; resto → inválido
        $pos = array_search($choice, $keys, true);

        if ($pos === 0) {
            Cliente::find($session->id_cliente)?->increment('contador_reservas_deportes');
            $session->mergeEstado([
                'estado_actual'  => 'COMPLETADO',
                'rama_activa'    => 'DEPORTES',
                'contador_invalidos' => 0,
            ]);
            return [BotMessages::render('MSG_DEP_01')];
        }

        if ($pos === 1) {
            $session->mergeEstado([
                'estado_actual'      => 'RECOLECTANDO_DATOS',
                'rama_activa'        => 'RESTAURANTE',
                'subtipo_activo'     => null,
                'current_step'       => 'fecha',
                'contador_invalidos' => 0,
                'datos_parciales'    => [],
            ]);
            return [BotMessages::render('MSG_RES_01')];
        }

        if ($pos === 2) {
            $session->mergeEstado([
                'estado_actual'      => 'RECOLECTANDO_DATOS',
                'rama_activa'        => 'EVENTOS',
                'subtipo_activo'     => null,
                'current_step'       => 'tipo_evento',
                'contador_invalidos' => 0,
                'datos_parciales'    => [],
            ]);
            return [BotMessages::render('MSG_EVT_01')];
        }

        return $this->handleInvalid(
            $session,
            fn () => [BotMessages::render('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $nombre])]
        );
    }

    private function handleRecolectandoDatos(BotSession $session, string $text): array
    {
        return match ($session->rama_activa) {
            'RESTAURANTE' => $this->stepRestaurante($session, $text),
            'EVENTOS'     => $this->stepEventos($session, $text),
            default       => $this->escalate($session, 'SOLICITUD_CLIENTE'),
        };
    }

    // ─── RESTAURANTE ──────────────────────────────────────────────────────────

    private function stepRestaurante(BotSession $session, string $text): array
    {
        $step = $session->current_step;

        switch ($step) {
            case 'fecha':
                $optsFecha = BotMessages::parseOptions('MSG_RES_01');
                unset($optsFecha['0']);
                if (!isset($optsFecha[strtoupper(trim($text))])) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_01')]);
                }
                $fecha = BotMessages::resolveFechaRestaurante($text);
                $esFutura = Carbon::createFromFormat('d/m/y', $fecha)->diffInDays(Carbon::today(), false) < -7;
                $this->saveDato($session, 'fecha', $fecha);
                $this->saveDato($session, 'fecha_es_futura', $esFutura);
                return $this->nextStep($session, 'hora', 'MSG_RES_02');

            case 'hora':
                $hora = BotMessages::resolveOption('MSG_RES_02', $text);
                if (!$hora) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_02')]);
                }
                $this->saveDato($session, 'hora', $hora);
                return $this->nextStep($session, 'numero_personas', 'MSG_RES_03');

            case 'numero_personas':
                $personas = BotMessages::resolveOption('MSG_RES_03', $text);
                if (!$personas) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_03')]);
                }
                $this->saveDato($session, 'numero_personas', $personas);
                $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? '';
                $this->saveDato($session, 'nombre_responsable', $nombre);
                return $this->skipMailIfKnown($session, 'MSG_RES_06');

            case 'mail_contacto':
                return $this->handleMailStep(
                    $session, $text,
                    fn () => $this->goToConfirmacion($session)
                );

            default:
                return $this->escalate($session, 'SOLICITUD_CLIENTE');
        }
    }

    // ─── EVENTOS ──────────────────────────────────────────────────────────────

    private function stepEventos(BotSession $session, string $text): array
    {
        $step    = $session->current_step;
        $subtipo = $session->subtipo_activo;

        // Paso universal: tipo_evento
        if ($step === 'tipo_evento') {
            $optsEvt = BotMessages::parseOptions('MSG_EVT_01');
            $keysEvt = array_values(array_filter(array_keys($optsEvt), fn ($k) => $k !== '0'));
            $upper   = strtoupper(trim($text));
            if (!isset($optsEvt[$upper])) {
                return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_01')]);
            }
            $this->saveDato($session, 'tipo_evento', $optsEvt[$upper]);
            // 1ra opción = evento privado → asesor
            if ($upper === ($keysEvt[0] ?? '1')) {
                return $this->escalate($session, 'SOLICITUD_CLIENTE');
            }
            // 2da opción = cumpleaños niños, resto → general
            $nuevoSubtipo = ($upper === ($keysEvt[1] ?? '2')) ? 'NINOS' : 'GENERAL_EVT';
            $this->pushHistory($session);
            $session->mergeEstado(['subtipo_activo' => $nuevoSubtipo, 'contador_invalidos' => 0]);
            if ($nuevoSubtipo === 'NINOS') {
                $session->mergeEstado(['current_step' => 'pack_seleccionado']);
                return [BotMessages::render('MSG_EVT_NINOS_PACK')];
            }
            // GENERAL_EVT
            $session->mergeEstado(['current_step' => 'fecha']);
            return [BotMessages::render('MSG_EVT_02')];
        }

        return $subtipo === 'NINOS'
            ? $this->stepNinos($session, $text)
            : $this->stepGeneralEvt($session, $text);
    }

    private function stepNinos(BotSession $session, string $text): array
    {
        $step = $session->current_step;

        switch ($step) {
            case 'pack_seleccionado':
                if (!BotMessages::resolveOption('MSG_EVT_NINOS_PACK', $text)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_NINOS_PACK')]);
                }
                $this->saveDato($session, 'pack_seleccionado', strtoupper(trim($text)));
                return $this->nextStep($session, 'fecha', 'MSG_EVT_02');

            case 'fecha':
                $dateNinos = $this->parseEventDate($text);
                if (!$dateNinos || !$dateNinos->isAfter(Carbon::today())) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_02')]);
                }
                $fechaStrNinos  = $dateNinos->format('d/m/y');
                $esFeriadoNinos = Feriado::esFeriado($fechaStrNinos);
                $this->saveDato($session, 'fecha', $fechaStrNinos);
                $this->saveDato($session, 'es_feriado', $esFeriadoNinos ? 1 : 2);
                $this->pushHistory($session);
                $session->mergeEstado(['current_step' => 'hora_inicio', 'contador_invalidos' => 0]);
                $msgs = [];
                if ($esFeriadoNinos) $msgs[] = BotMessages::render('MSG_EVT_FERIADO_AVISO');
                $msgs[] = BotMessages::render('MSG_EVT_03_ENTERO');
                return $msgs;

            case 'hora_inicio':
                $horaNinos = $this->parseEventTime($text);
                if (!$horaNinos) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_03_ENTERO')]);
                }
                $horaIntNinos = (int) explode(':', $horaNinos)[0];
                if ($horaIntNinos < 8 || $horaIntNinos > 23) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_03_ENTERO')]);
                }
                $this->saveDato($session, 'hora_inicio', $horaIntNinos);
                return $this->nextStep($session, 'numero_ninos', 'MSG_EVT_05');

            case 'numero_ninos':
                if (!ctype_digit($text) || (int)$text < 1) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_05')]);
                }
                if ((int)$text > 50) {
                    return $this->escalate($session, 'CAPACIDAD_EXCEDIDA');
                }
                $ninos = (int)$text;
                $this->saveDato($session, 'numero_ninos', $ninos);

                // Calcular canchas y coordinadores
                [$canchas, $coordinadores] = $this->calcularCanchasCoordinadores($ninos);
                $this->saveDato($session, 'num_canchas', $canchas);
                $this->saveDato($session, 'num_coordinadores', $coordinadores);

                // Paso INFORMATIVO: calcular y enviar MSG_EVT_COSTO_MENU, luego continuar
                $pack     = $session->getDatos('pack_seleccionado', '1');
                $precio   = CostoEvento::precio('pack_' . $pack . '_menu');
                $costo    = $ninos * $precio;
                $infoMsg  = BotMessages::render('MSG_EVT_COSTO_MENU', [
                    'numero_ninos'        => $ninos,
                    'pack_label'          => BotMessages::packLabel($pack),
                    'costo_menu_calculado' => number_format($costo, 0, ',', '.'),
                ]);
                $this->pushHistory($session);
                $session->mergeEstado(['current_step' => 'menu_preferido', 'contador_invalidos' => 0]);
                return [$infoMsg, BotMessages::render('MSG_EVT_MENU')];

            case 'menu_preferido':
                $menu = BotMessages::resolveOption('MSG_EVT_MENU', $text);
                if (!$menu) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_MENU')]);
                }
                $this->saveDato($session, 'menu_preferido', $menu);
                $precioAdulto = CostoEvento::precio('menu_adulto');
                return $this->nextStep($session, 'numero_adultos', 'MSG_EVT_ADULTOS', [
                    'precio_menu_adulto' => number_format($precioAdulto, 0, ',', '.'),
                ]);

            case 'numero_adultos':
                if (!ctype_digit($text)) {
                    return $this->handleInvalid($session, fn () => [
                        BotMessages::render('MSG_EVT_ADULTOS', [
                            'precio_menu_adulto' => number_format(CostoEvento::precio('menu_adulto'), 0, ',', '.'),
                        ])
                    ]);
                }
                $adultos = (int)$text;
                $this->saveDato($session, 'numero_adultos', $adultos);
                if ($adultos > 0) {
                    $this->pushHistory($session);
                    $session->mergeEstado(['current_step' => 'menu_adultos', 'contador_invalidos' => 0]);
                    return [BotMessages::render('MSG_EVT_MENU_ADULTOS', ['numero_adultos' => $adultos])];
                }
                $this->saveDato($session, 'menu_adultos', 0);
                return $this->nextStep($session, 'alimentos_adicionales', 'MSG_EVT_ADICIONALES');

            case 'menu_adultos':
                $adultos = (int)$session->getDatos('numero_adultos', 0);
                if (!ctype_digit($text) || (int)$text < 0 || (int)$text > $adultos) {
                    return $this->handleInvalid($session, fn () => [
                        BotMessages::render('MSG_EVT_MENU_ADULTOS', ['numero_adultos' => $adultos])
                    ]);
                }
                $this->saveDato($session, 'menu_adultos', (int)$text);
                return $this->nextStep($session, 'alimentos_adicionales', 'MSG_EVT_ADICIONALES');

            case 'alimentos_adicionales':
                if (strtolower($text) === 'ninguno') {
                    $this->saveDato($session, 'alimentos_adicionales', []);
                    return $this->nextStep($session, 'extras_texto', 'MSG_EVT_EXTRAS');
                }
                // Validar lista de claves contra las opciones del mensaje
                $optsAdic = BotMessages::parseOptions('MSG_EVT_ADICIONALES');
                unset($optsAdic['0'], $optsAdic['NINGUNO']);
                $partes   = array_map('trim', explode(',', $text));
                $validos  = array_filter($partes, fn ($p) => isset($optsAdic[strtoupper($p)]));
                if (count($validos) !== count($partes) || empty($validos)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_ADICIONALES')]);
                }
                $ids = array_values(array_unique(array_map('intval', $validos)));
                $this->saveDato($session, 'alimentos_adicionales', $ids);
                $this->saveDato($session, 'adicionales_pendientes_qty', $ids);
                $this->saveDato($session, 'adicionales_qtys', []);
                // Preguntar primer adicional
                return $this->askNextAdicionalQty($session);

            default:
                // Sub-pasos de cantidad de adicionales
                if (str_starts_with($step, 'adicional_qty_')) {
                    return $this->handleAdicionalQty($session, $text);
                }
                return $this->stepNinosLate($session, $text);
        }
    }

    private function handleAdicionalQty(BotSession $session, string $text): array
    {
        if (!ctype_digit($text) || (int)$text < 1) {
            $step    = $session->current_step;
            $itemId  = (int)substr($step, strlen('adicional_qty_'));
            return $this->handleInvalid($session, fn () => [
                BotMessages::render('MSG_EVT_ADICIONAL_QTY', ['item_name' => BotMessages::adicionalLabel($itemId)])
            ]);
        }

        $step    = $session->current_step;
        $itemId  = (int)substr($step, strlen('adicional_qty_'));
        $qtys    = $session->getDatos('adicionales_qtys', []);
        $qtys[$itemId] = (int)$text;
        $this->saveDato($session, 'adicionales_qtys', $qtys);

        // Sacar este item de pendientes
        $pendientes = array_values(array_filter(
            $session->getDatos('adicionales_pendientes_qty', []),
            fn ($id) => $id !== $itemId
        ));
        $this->saveDato($session, 'adicionales_pendientes_qty', $pendientes);
        $session->mergeEstado(['contador_invalidos' => 0]);

        if (!empty($pendientes)) {
            return $this->askNextAdicionalQty($session);
        }

        return $this->nextStep($session, 'extras_texto', 'MSG_EVT_EXTRAS');
    }

    private function askNextAdicionalQty(BotSession $session): array
    {
        $pendientes = $session->getDatos('adicionales_pendientes_qty', []);
        $itemId     = $pendientes[0];
        $this->pushHistory($session);
        $session->mergeEstado(['current_step' => 'adicional_qty_' . $itemId, 'contador_invalidos' => 0]);
        return [BotMessages::render('MSG_EVT_ADICIONAL_QTY', ['item_name' => BotMessages::adicionalLabel($itemId)])];
    }

    private function stepNinosLate(BotSession $session, string $text): array
    {
        $step = $session->current_step;

        switch ($step) {
            case 'extras_texto':
                $tieneExtras = strtolower($text) !== 'ninguno';
                $this->saveDato($session, 'extras_texto', $text);
                $this->saveDato($session, 'tiene_extras', $tieneExtras);
                return $this->skipMailIfKnown($session, 'MSG_EVT_MAIL');

            case 'mail_contacto':
                return $this->handleMailStep(
                    $session, $text,
                    fn () => $this->nextStep($session, 'nombre_responsable', 'MSG_EVT_07'),
                    noAllowed: true
                );

            case 'nombre_responsable':
                $opts07  = BotMessages::parseOptions('MSG_EVT_07');
                $keys07  = array_values(array_filter(array_map('strval', array_keys($opts07)), fn ($k) => $k !== '0'));
                $upper07 = strtoupper(trim($text));
                if (!in_array($upper07, array_map('strval', array_keys($opts07)), true)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_07')]);
                }
                if ($upper07 === ($keys07[0] ?? '1')) {
                    $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? '';
                    $this->saveDato($session, 'nombre_responsable', $nombre);
                    return $this->goToConfirmacion($session);
                }
                $this->pushHistory($session);
                $session->mergeEstado(['current_step' => 'nombre_responsable_custom', 'contador_invalidos' => 0]);
                return [BotMessages::render('MSG_EVT_07_CUSTOM')];

            case 'nombre_responsable_custom':
                $this->saveDato($session, 'nombre_responsable', $text);
                return $this->goToConfirmacion($session);

            default:
                return $this->escalate($session, 'SOLICITUD_CLIENTE');
        }
    }

    private function stepGeneralEvt(BotSession $session, string $text): array
    {
        $step = $session->current_step;

        switch ($step) {
            case 'fecha':
                $dateGen = $this->parseEventDate($text);
                if (!$dateGen || !$dateGen->isAfter(Carbon::today())) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_02')]);
                }
                $fechaStrGen  = $dateGen->format('d/m/y');
                $esFeriadoGen = Feriado::esFeriado($fechaStrGen);
                $this->saveDato($session, 'fecha', $fechaStrGen);
                $this->saveDato($session, 'es_feriado', $esFeriadoGen ? 1 : 2);
                $this->pushHistory($session);
                $session->mergeEstado(['current_step' => 'hora_inicio', 'contador_invalidos' => 0]);
                $msgsGen = [];
                if ($esFeriadoGen) $msgsGen[] = BotMessages::render('MSG_EVT_FERIADO_AVISO');
                $msgsGen[] = BotMessages::render('MSG_EVT_03_HHMM');
                return $msgsGen;

            case 'hora_inicio':
                $horaGen = $this->parseEventTime($text);
                if (!$horaGen) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_03_HHMM')]);
                }
                $this->saveDato($session, 'hora_inicio', $horaGen);
                return $this->nextStep($session, 'numero_personas', 'MSG_EVT_PERSONAS');

            case 'numero_personas':
                if (!ctype_digit($text) || (int)$text < 1 || (int)$text > 999) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_PERSONAS')]);
                }
                $this->saveDato($session, 'numero_personas', (int)$text);
                return $this->nextStep($session, 'nombre_responsable', 'MSG_EVT_07');

            case 'nombre_responsable':
                $optsG07  = BotMessages::parseOptions('MSG_EVT_07');
                $keysG07  = array_values(array_filter(array_map('strval', array_keys($optsG07)), fn ($k) => $k !== '0'));
                $upperG07 = strtoupper(trim($text));
                if (!in_array($upperG07, array_map('strval', array_keys($optsG07)), true)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_07')]);
                }
                if ($upperG07 === ($keysG07[0] ?? '1')) {
                    $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? '';
                    $this->saveDato($session, 'nombre_responsable', $nombre);
                    return $this->skipMailIfKnown($session, 'MSG_EVT_MAIL');
                }
                $this->pushHistory($session);
                $session->mergeEstado(['current_step' => 'nombre_responsable_custom', 'contador_invalidos' => 0]);
                return [BotMessages::render('MSG_EVT_07_CUSTOM')];

            case 'nombre_responsable_custom':
                $this->saveDato($session, 'nombre_responsable', $text);
                return $this->skipMailIfKnown($session, 'MSG_EVT_MAIL');

            case 'mail_contacto':
                return $this->handleMailStep(
                    $session, $text,
                    fn () => $this->goToConfirmacion($session),
                    noAllowed: true
                );

            default:
                return $this->escalate($session, 'SOLICITUD_CLIENTE');
        }
    }

    // ─── CONFIRMACION ─────────────────────────────────────────────────────────

    private function handleConfirmacion(BotSession $session, string $text): array
    {
        // Determinar claves de confirmación dinámicamente desde el mensaje activo
        $msgId   = $session->rama_activa === 'RESTAURANTE'
            ? ($session->getDatos('fecha_es_futura', false) ? 'MSG_RES_CONFIRMACION_FUTURA' : 'MSG_RES_CONFIRMACION')
            : 'MSG_CONFIRMACION';
        $opts    = BotMessages::parseOptions($msgId, ['resumen' => '']);
        $keys    = array_values(array_filter(array_keys($opts), fn ($k) => $k !== '0'));
        $upper   = strtoupper(trim($text));

        // 1ra opción = confirmar
        if ($upper === ($keys[0] ?? 'SI')) {
            return $this->confirmarReserva($session);
        }

        // 2da opción = cambiar
        if (isset($keys[1]) && $upper === $keys[1]) {
            $this->saveDato($session, 'cambiando_paso', null);
            $session->mergeEstado(['estado_actual' => 'CAMBIANDO_DATO', 'contador_invalidos' => 0]);
            if ($session->rama_activa === 'RESTAURANTE') {
                return [BotMessages::render('MSG_RES_CAMBIAR')];
            }
            $msgId = $session->subtipo_activo === 'NINOS' ? 'MSG_EVT_NINOS_CAMBIAR' : 'MSG_EVT_CAMBIAR';
            return [BotMessages::render($msgId)];
        }

        return $this->handleInvalid($session, fn () => [$this->buildConfirmacionMsg($session)]);
    }

    private function confirmarReserva(BotSession $session): array
    {
        $datos    = $session->datos_parciales ?? [];
        $rama     = $session->rama_activa;
        $esFutura = $datos['fecha_es_futura'] ?? false;

        $estado = match(true) {
            $rama === 'EVENTOS'                  => 'PENDIENTE_CONFIRMACION',
            $rama === 'RESTAURANTE' && $esFutura => 'PENDIENTE_CONFIRMACION',
            default                              => 'CONFIRMADA',
        };

        $reserva = Reserva::create([
            'id_cliente'     => $session->id_cliente,
            'rama_servicio'  => $rama,
            'subtipo'        => $session->subtipo_activo,
            'estado_reserva' => $estado,
            'datos'          => $datos,
            'tiene_extras'   => $datos['tiene_extras'] ?? false,
            'presupuesto_total' => $this->calcularPresupuesto($session)['total'] ?? null,
        ]);

        // Incrementar contador y actualizar CRM
        $counter = match($rama) {
            'RESTAURANTE' => 'contador_reservas_restaurante',
            'EVENTOS'     => 'contador_reservas_eventos',
            default       => null,
        };
        if ($counter) Cliente::find($session->id_cliente)?->increment($counter);

        $cliente = Cliente::find($session->id_cliente);
        if ($cliente) {
            $crm = $cliente->crmOrCreate();
            $crmUpdate = ['fecha_ultimo_evento' => now()->toDateString()];
            $presupuesto = $this->calcularPresupuesto($session)['total'] ?? 0;
            if ($presupuesto > 0) {
                $crmUpdate['valor_lifetime'] = $crm->valor_lifetime + $presupuesto;
            }
            $crm->update($crmUpdate);
        }

        $session->mergeEstado(['estado_actual' => 'COMPLETADO', 'contador_invalidos' => 0]);

        return [$estado === 'PENDIENTE_CONFIRMACION'
            ? BotMessages::render('MSG_RESERVA_PRECONFIRMADA')
            : BotMessages::render('MSG_RESERVA_EXITOSA')];
    }

    // ─── COMPLETADO ───────────────────────────────────────────────────────────

    private function handleCompletado(BotSession $session, string $text): array
    {
        // Cualquier mensaje reinicia desde MENU_PRINCIPAL
        $session->mergeEstado([
            'estado_actual'   => 'MENU_PRINCIPAL',
            'rama_activa'     => null,
            'subtipo_activo'  => null,
            'current_step'    => null,
            'datos_parciales' => [],
            'contador_invalidos' => 0,
        ]);
        $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? 'cliente';
        return [BotMessages::render('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $nombre])];
    }

    // ─── CAMBIAR DATO ─────────────────────────────────────────────────────────

    private function handleCambiandoDato(BotSession $session, string $text): array
    {
        return $session->rama_activa === 'RESTAURANTE'
            ? $this->handleCambiandoDatoRestaurante($session, $text)
            : $this->handleCambiandoDatoEvento($session, $text);
    }

    private function handleCambiandoDatoRestaurante(BotSession $session, string $text): array
    {
        $datos         = $session->datos_parciales ?? [];
        $cambiandoPaso = $datos['cambiando_paso'] ?? null;

        if ($cambiandoPaso === null) {
            $opts      = BotMessages::parseOptions('MSG_RES_CAMBIAR');
            $strKeys   = array_map('strval', array_keys($opts));
            $nonZero   = array_values(array_filter($strKeys, fn ($k) => $k !== '0'));
            $upperText = strtoupper(trim($text));
            if (!in_array($upperText, $strKeys, true)) {
                return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_CAMBIAR')]);
            }
            $stepSequence = ['fecha', 'hora', 'numero_personas', 'nombre_responsable', 'mail_contacto'];
            $pos  = array_search($upperText, $nonZero, true);
            $paso = $pos !== false ? ($stepSequence[$pos] ?? null) : null;
            if ($paso === null) {
                return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_CAMBIAR')]);
            }
            $this->saveDato($session, 'cambiando_paso', $paso);
            $session->mergeEstado(['contador_invalidos' => 0]);
            if ($paso === 'mail_contacto') {
                $email = Cliente::find($session->id_cliente)?->mail_contacto;
                if ($email) {
                    $this->saveDato($session, '_mail_conocido', $email);
                    return [BotMessages::render('MSG_CONFIRMAR_MAIL', ['mail' => $email])];
                }
                return [BotMessages::render('MSG_RES_06')];
            }
            $msgMap = [
                'fecha'              => 'MSG_RES_01',
                'hora'               => 'MSG_RES_02',
                'numero_personas'    => 'MSG_RES_03',
                'nombre_responsable' => 'MSG_RES_05_CUSTOM',
            ];
            return [BotMessages::render($msgMap[$paso])];
        }

        if ($cambiandoPaso === 'mail_contacto') {
            return $this->handleMailStep($session, $text, function () use ($session) {
                $this->saveDato($session, 'cambiando_paso', null);
                $session->mergeEstado(['estado_actual' => 'CONFIRMACION', 'contador_invalidos' => 0]);
                return [$this->buildConfirmacionMsg($session)];
            });
        }

        $result = $this->validateAndSaveCambio($session, $cambiandoPaso, $text);
        if ($result === false) {
            $msgMap = [
                'fecha'              => 'MSG_RES_01',
                'hora'               => 'MSG_RES_02',
                'numero_personas'    => 'MSG_RES_03',
                'nombre_responsable' => 'MSG_RES_05_CUSTOM',
                'mail_contacto'      => 'MSG_RES_06',
            ];
            return $this->handleInvalid($session, fn () => [BotMessages::render($msgMap[$cambiandoPaso] ?? 'MSG_RES_01')]);
        }

        $this->saveDato($session, 'cambiando_paso', null);
        $session->mergeEstado(['estado_actual' => 'CONFIRMACION', 'contador_invalidos' => 0]);
        return [$this->buildConfirmacionMsg($session)];
    }

    private function handleCambiandoDatoEvento(BotSession $session, string $text): array
    {
        $datos         = $session->datos_parciales ?? [];
        $cambiandoPaso = $datos['cambiando_paso'] ?? null;
        $subtipo       = $session->subtipo_activo;
        $msgCambiar    = $subtipo === 'NINOS' ? 'MSG_EVT_NINOS_CAMBIAR' : 'MSG_EVT_CAMBIAR';

        if ($cambiandoPaso === null) {
            $opts    = BotMessages::parseOptions($msgCambiar);
            $strKeys = array_map('strval', array_keys($opts));
            $nonZero = array_values(array_filter($strKeys, fn ($k) => $k !== '0'));
            $upper   = strtoupper(trim($text));
            if (!in_array($upper, $strKeys, true)) {
                return $this->handleInvalid($session, fn () => [BotMessages::render($msgCambiar)]);
            }
            $sequence = $subtipo === 'NINOS'
                ? ['fecha', 'hora_inicio', 'nombre_responsable', 'mail_contacto']
                : ['fecha', 'hora_inicio', 'numero_personas', 'nombre_responsable', 'mail_contacto'];
            $pos  = array_search($upper, $nonZero, true);
            $paso = $pos !== false ? ($sequence[$pos] ?? null) : null;
            if ($paso === null) {
                return $this->handleInvalid($session, fn () => [BotMessages::render($msgCambiar)]);
            }
            $this->saveDato($session, 'cambiando_paso', $paso);
            $session->mergeEstado(['contador_invalidos' => 0]);
            if ($paso === 'mail_contacto') {
                $email = Cliente::find($session->id_cliente)?->mail_contacto;
                if ($email) {
                    $this->saveDato($session, '_mail_conocido', $email);
                    return [BotMessages::render('MSG_CONFIRMAR_MAIL', ['mail' => $email])];
                }
                return [BotMessages::render('MSG_EVT_MAIL')];
            }
            $msgMap = [
                'fecha'              => 'MSG_EVT_02',
                'hora_inicio'        => $subtipo === 'NINOS' ? 'MSG_EVT_03_ENTERO' : 'MSG_EVT_03_HHMM',
                'numero_personas'    => 'MSG_EVT_PERSONAS',
                'nombre_responsable' => 'MSG_EVT_07',
            ];
            return [BotMessages::render($msgMap[$paso])];
        }

        if ($cambiandoPaso === 'mail_contacto') {
            return $this->handleMailStep($session, $text, function () use ($session) {
                $this->saveDato($session, 'cambiando_paso', null);
                $session->mergeEstado(['estado_actual' => 'CONFIRMACION', 'contador_invalidos' => 0]);
                return [$this->buildConfirmacionMsg($session)];
            }, noAllowed: true);
        }

        $result = $this->validateAndSaveCambioEvento($session, $cambiandoPaso, $text);
        if ($result === false) {
            $msgMap = [
                'fecha'                     => 'MSG_EVT_02',
                'hora_inicio'               => $subtipo === 'NINOS' ? 'MSG_EVT_03_ENTERO' : 'MSG_EVT_03_HHMM',
                'numero_personas'           => 'MSG_EVT_PERSONAS',
                'nombre_responsable'        => 'MSG_EVT_07',
                'nombre_responsable_custom' => 'MSG_EVT_07_CUSTOM',
                'mail_contacto'             => 'MSG_EVT_MAIL',
            ];
            return $this->handleInvalid($session, fn () => [BotMessages::render($msgMap[$cambiandoPaso] ?? 'MSG_EVT_02')]);
        }
        if ($result === 'ask_custom_nombre') {
            $this->saveDato($session, 'cambiando_paso', 'nombre_responsable_custom');
            $session->mergeEstado(['contador_invalidos' => 0]);
            return [BotMessages::render('MSG_EVT_07_CUSTOM')];
        }

        $this->saveDato($session, 'cambiando_paso', null);
        $session->mergeEstado(['estado_actual' => 'CONFIRMACION', 'contador_invalidos' => 0]);

        $confirmMsg = $this->buildConfirmacionMsg($session);

        // Si cambió la fecha y ahora es feriado, avisar antes de mostrar la confirmación
        if ($cambiandoPaso === 'fecha' && ($session->datos_parciales['es_feriado'] ?? 2) === 1) {
            return [BotMessages::render('MSG_EVT_FERIADO_AVISO'), $confirmMsg];
        }

        return [$confirmMsg];
    }

    private function validateAndSaveCambioEvento(BotSession $session, string $campo, string $text): mixed
    {
        $subtipo = $session->subtipo_activo;

        switch ($campo) {
            case 'fecha':
                $date = $this->parseEventDate($text);
                if (!$date || !$date->isAfter(Carbon::today())) return false;
                $fechaStr  = $date->format('d/m/y');
                $esFeriado = Feriado::esFeriado($fechaStr);
                $this->saveDato($session, 'fecha', $fechaStr);
                $this->saveDato($session, 'es_feriado', $esFeriado ? 1 : 2);
                return true;

            case 'hora_inicio':
                $hora = $this->parseEventTime($text);
                if (!$hora) return false;
                if ($subtipo === 'NINOS') {
                    $h = (int) explode(':', $hora)[0];
                    if ($h < 8 || $h > 23) return false;
                    $this->saveDato($session, 'hora_inicio', $h);
                } else {
                    $this->saveDato($session, 'hora_inicio', $hora);
                }
                return true;

            case 'numero_personas':
                if (!ctype_digit($text) || (int)$text < 1 || (int)$text > 999) return false;
                $this->saveDato($session, 'numero_personas', (int)$text);
                return true;

            case 'nombre_responsable':
                $optsE  = BotMessages::parseOptions('MSG_EVT_07');
                $keysE  = array_values(array_filter(array_map('strval', array_keys($optsE)), fn ($k) => $k !== '0'));
                $upperE = strtoupper(trim($text));
                if (!in_array($upperE, array_map('strval', array_keys($optsE)), true)) return false;
                if ($upperE === ($keysE[0] ?? '1')) {
                    $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? '';
                    $this->saveDato($session, 'nombre_responsable', $nombre);
                    return true;
                }
                return 'ask_custom_nombre';

            case 'nombre_responsable_custom':
                $this->saveDato($session, 'nombre_responsable', $text);
                return true;

            case 'mail_contacto':
                $lower = strtolower($text);
                if ($lower !== 'no' && !$this->isValidEmail($text)) return false;
                $this->saveMailCliente($session, $lower === 'no' ? null : $text);
                return true;

            default:
                return false;
        }
    }

    private function validateAndSaveCambio(BotSession $session, string $campo, string $text): mixed
    {
        switch ($campo) {
            case 'fecha':
                if (!in_array($text, ['1','2','3','4','5','6','7'])) return false;
                $fecha = BotMessages::resolveFechaRestaurante($text);
                $esFutura = Carbon::createFromFormat('d/m/y', $fecha)->diffInDays(Carbon::today(), false) < -7;
                $this->saveDato($session, 'fecha', $fecha);
                $this->saveDato($session, 'fecha_es_futura', $esFutura);
                return true;
            case 'hora':
                $hora = BotMessages::resolveOption('MSG_RES_02', $text);
                if (!$hora) return false;
                $this->saveDato($session, 'hora', $hora);
                return true;
            case 'numero_personas':
                $personas = BotMessages::resolveOption('MSG_RES_03', $text);
                if (!$personas) return false;
                $this->saveDato($session, 'numero_personas', $personas);
                return true;
            case 'nombre_responsable':
                if (trim($text) === '') return false;
                $this->saveDato($session, 'nombre_responsable', trim($text));
                return true;
            case 'mail_contacto':
                if (!$this->isValidEmail($text)) return false;
                $this->saveMailCliente($session, $text);
                return true;
            default:
                return false;
        }
    }

    // ─── ESCALADO HUMANO ──────────────────────────────────────────────────────

    private function escalate(BotSession $session, string $motivo): array
    {
        $session->mergeEstado([
            'estado_previo_pausa' => $session->estado_actual,
            'estado_actual'       => 'PAUSADO',
            'motivo_pausa'        => $motivo,
            'timestamp_pausa'     => Carbon::now(),
            'contador_invalidos'  => 0,
        ]);
        return [BotMessages::render('MSG_ESCALADO_HUMANO')];
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    private function handleInvalid(BotSession $session, \Closure $retryMsg): array
    {
        $contador = ($session->contador_invalidos ?? 0) + 1;
        $session->mergeEstado(['contador_invalidos' => $contador]);

        if ($contador >= 2) {
            return $this->escalate($session, 'OPCIONES_INVALIDAS_REITERADAS');
        }

        return array_merge([BotMessages::render('MSG_OPCION_INVALIDA')], $retryMsg());
    }

    private function nextStep(BotSession $session, string $step, string $msgId, array $vars = []): array
    {
        $this->pushHistory($session);
        $session->mergeEstado(['current_step' => $step, 'contador_invalidos' => 0]);
        return [BotMessages::render($msgId, $vars)];
    }

    private function saveDato(BotSession $session, string $key, mixed $value): void
    {
        $datos        = $session->datos_parciales ?? [];
        $datos[$key]  = $value;
        $session->datos_parciales = $datos;
        $session->save();
    }

    private function goToConfirmacion(BotSession $session): array
    {
        $this->pushHistory($session);
        $session->mergeEstado(['estado_actual' => 'CONFIRMACION', 'contador_invalidos' => 0]);
        return [$this->buildConfirmacionMsg($session)];
    }

    private function buildConfirmacionMsg(BotSession $session): string
    {
        $rama     = $session->rama_activa;
        $esFutura = $session->getDatos('fecha_es_futura', false);
        $resumen  = $this->buildResumen($session);

        if ($rama === 'RESTAURANTE') {
            $msgId = $esFutura ? 'MSG_RES_CONFIRMACION_FUTURA' : 'MSG_RES_CONFIRMACION';
        } else {
            // Para EVENTOS el presupuesto va en el resumen
            $msgId = 'MSG_CONFIRMACION';
        }

        return BotMessages::render($msgId, ['resumen' => $resumen]);
    }

    private function buildResumen(BotSession $session): string
    {
        $datos = $session->datos_parciales ?? [];
        $rama  = $session->rama_activa;
        $lines = [];

        if ($rama === 'RESTAURANTE') {
            if (!empty($datos['fecha']))             $lines[] = "📅 Fecha: {$datos['fecha']}";
            if (!empty($datos['hora']))              $lines[] = "🕐 Horario: {$datos['hora']}";
            if (!empty($datos['numero_personas']))   $lines[] = "👥 Personas: {$datos['numero_personas']}";
            if (!empty($datos['nombre_responsable']))$lines[] = "👤 Responsable: {$datos['nombre_responsable']}";
            if (!empty($datos['mail_contacto']))     $lines[] = "📧 Mail: {$datos['mail_contacto']}";
        } elseif ($rama === 'EVENTOS') {
            $subtipo = $session->subtipo_activo;
            if (!empty($datos['tipo_evento']))       $lines[] = "🎉 Tipo: {$datos['tipo_evento']}";
            if (!empty($datos['fecha']))             $lines[] = "📅 Fecha: {$datos['fecha']}";
            if (!empty($datos['hora_inicio']))       $lines[] = "🕐 Hora inicio: {$datos['hora_inicio']}";

            if ($subtipo === 'NINOS') {
                if (!empty($datos['pack_seleccionado'])) $lines[] = "🎁 Pack: " . BotMessages::packLabel($datos['pack_seleccionado']);
                if (!empty($datos['numero_ninos']))      $lines[] = "👦 Niños: {$datos['numero_ninos']}";
                if (!empty($datos['menu_preferido']))    $lines[] = "🍕 Menú niños: {$datos['menu_preferido']}";
                if (isset($datos['numero_adultos']))     $lines[] = "🧑 Adultos: {$datos['numero_adultos']}";
                if (!empty($datos['menu_adultos']))      $lines[] = "🍽️ Menú adultos: {$datos['menu_adultos']}";

                // Adicionales
                $ids  = $datos['alimentos_adicionales'] ?? [];
                $qtys = $datos['adicionales_qtys'] ?? [];
                foreach ($ids as $id) {
                    $qty     = $qtys[$id] ?? '?';
                    $lines[] = "➕ " . BotMessages::adicionalLabel((int)$id) . ": x{$qty}";
                }

                if (!empty($datos['extras_texto']) && strtolower($datos['extras_texto']) !== 'ninguno') {
                    $lines[] = "📝 Extras: {$datos['extras_texto']}";
                }
            } else {
                if (!empty($datos['numero_personas'])) $lines[] = "👥 Personas: {$datos['numero_personas']}";
            }

            if (!empty($datos['mail_contacto']))       $lines[] = "📧 Mail: {$datos['mail_contacto']}";
            if (!empty($datos['nombre_responsable']))  $lines[] = "👤 Responsable: {$datos['nombre_responsable']}";

            // Presupuesto para NINOS
            if ($subtipo === 'NINOS') {
                $ppto = $this->calcularPresupuesto($session);
                if ($ppto['total'] > 0) {
                    $lines[] = "";
                    $lines[] = "💰 *Presupuesto estimado:*";
                    foreach ($ppto['detalle'] as $linea) {
                        $lines[] = $linea;
                    }
                    if ($ppto['recargo_feriado'] > 0) {
                        $lines[] = "⚠️ Recargo feriado (×1.30): $" . number_format($ppto['recargo_feriado'], 0, ',', '.');
                    }
                    $lines[] = "─────────────────";
                    $lines[] = "*TOTAL ESTIMADO: $" . number_format($ppto['total'], 0, ',', '.') . "*";
                }
            } elseif (($datos['es_feriado'] ?? 2) === 1) {
                $lines[] = "";
                $lines[] = "⚠️ *Fecha feriado: se aplicará un recargo del 30% sobre el costo del evento.*";
            }
        }

        return implode("\n", $lines);
    }

    private function calcularPresupuesto(BotSession $session): array
    {
        if ($session->subtipo_activo !== 'NINOS') return ['total' => 0, 'detalle' => [], 'recargo_feriado' => 0];

        $datos = $session->datos_parciales ?? [];

        $pack            = $datos['pack_seleccionado'] ?? '1';
        $ninos           = (int)($datos['numero_ninos'] ?? 0);
        $canchas         = (int)($datos['num_canchas'] ?? 1);
        $coordinadores   = (int)($datos['num_coordinadores'] ?? 2);
        $menuAdultos     = (int)($datos['menu_adultos'] ?? 0);
        $ids             = $datos['alimentos_adicionales'] ?? [];
        $qtys            = $datos['adicionales_qtys'] ?? [];
        $esFeriado       = ($datos['es_feriado'] ?? 2) === 1;

        $pMenuNinos    = CostoEvento::precio('pack_' . $pack . '_menu');
        $pCancha       = CostoEvento::precio('cancha');
        $pCoord        = CostoEvento::precio('coordinador');
        $pMenuAdulto   = CostoEvento::precio('menu_adulto');

        $subtMenuNinos  = $ninos * $pMenuNinos;
        $subtCanchas    = $canchas * $pCancha;
        $subtCoords     = $coordinadores * $pCoord;
        $subtMenuAdults = $menuAdultos * $pMenuAdulto;

        $subtAdicionales = 0;
        $detAdicionales  = [];
        foreach ($ids as $id) {
            $qty      = (int)($qtys[$id] ?? 0);
            $precio   = CostoEvento::precio(BotMessages::adicionalConcepto((int)$id));
            $sub      = $qty * $precio;
            $subtAdicionales += $sub;
            $detAdicionales[] = BotMessages::adicionalLabel((int)$id) . " x{$qty}: $" . number_format($sub, 0, ',', '.');
        }

        $subtotal     = $subtMenuNinos + $subtCanchas + $subtCoords + $subtMenuAdults + $subtAdicionales;
        $recargo      = $esFeriado ? $subtotal * 0.30 : 0;
        $total        = $subtotal + $recargo;

        $detalle = [
            "Menú niños (" . BotMessages::packLabel($pack) . ") × {$ninos}: $" . number_format($subtMenuNinos, 0, ',', '.'),
            "Canchas × {$canchas}: $" . number_format($subtCanchas, 0, ',', '.'),
            "Coordinadores × {$coordinadores}: $" . number_format($subtCoords, 0, ',', '.'),
        ];
        if ($menuAdultos > 0) {
            $detalle[] = "Menú adultos × {$menuAdultos}: $" . number_format($subtMenuAdults, 0, ',', '.');
        }
        $detalle = array_merge($detalle, $detAdicionales);

        return compact('subtotal', 'recargo', 'total', 'detalle') + ['recargo_feriado' => $recargo];
    }

    private function calcularCanchasCoordinadores(int $ninos): array
    {
        return match(true) {
            $ninos <= 20 => [1, 2],
            $ninos <= 40 => [2, 4],
            default      => [3, 6],
        };
    }

    /**
     * Si el cliente ya tiene mail, muestra MSG_CONFIRMAR_MAIL para que confirme o cambie.
     * Si no tiene, muestra el mensaje de pedir mail ($mailMsgId).
     */
    private function skipMailIfKnown(BotSession $session, string $mailMsgId): array
    {
        $email = Cliente::find($session->id_cliente)?->mail_contacto;
        if ($email) {
            $this->saveDato($session, '_mail_conocido', $email);
            return $this->nextStep($session, 'mail_contacto', 'MSG_CONFIRMAR_MAIL', ['mail' => $email]);
        }
        return $this->nextStep($session, 'mail_contacto', $mailMsgId);
    }

    /**
     * Maneja el paso mail_contacto para los tres flujos.
     * Si hay un _mail_conocido guardado: acepta SI para confirmarlo o un nuevo mail para reemplazarlo.
     * $noAllowed = true habilita "no" para omitir mail (eventos).
     */
    private function handleMailStep(BotSession $session, string $text, callable $advance, bool $noAllowed = false): array
    {
        $datos        = $session->datos_parciales ?? [];
        $mailConocido = $datos['_mail_conocido'] ?? null;
        $upper        = strtoupper(trim($text));
        $lower        = strtolower(trim($text));

        if ($mailConocido && $upper === 'SI') {
            $this->saveDato($session, '_mail_conocido', null);
            $this->saveMailCliente($session, $mailConocido);
            return $advance();
        }

        if ($noAllowed && $lower === 'no') {
            $this->saveDato($session, '_mail_conocido', null);
            $this->saveMailCliente($session, null);
            return $advance();
        }

        if ($this->isValidEmail($text)) {
            $this->saveDato($session, '_mail_conocido', null);
            $this->saveMailCliente($session, $text);
            return $advance();
        }

        $errorMsg = $mailConocido
            ? BotMessages::render('MSG_CONFIRMAR_MAIL', ['mail' => $mailConocido])
            : BotMessages::render('MSG_RES_MAIL_INVALIDO');
        return $this->handleInvalid($session, fn () => [$errorMsg]);
    }

    /** Guarda el mail en datos_parciales Y en el perfil del cliente. */
    private function saveMailCliente(BotSession $session, ?string $mail): void
    {
        $this->saveDato($session, 'mail_contacto', $mail);
        if ($mail) {
            Cliente::find($session->id_cliente)?->update(['mail_contacto' => $mail]);
        }
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function pushHistory(BotSession $session): void
    {
        $datos     = $session->datos_parciales ?? [];
        $snapshot  = [
            'estado_actual'  => $session->estado_actual,
            'rama_activa'    => $session->rama_activa,
            'subtipo_activo' => $session->subtipo_activo,
            'current_step'   => $session->current_step,
            'datos'          => array_filter($datos, fn($k) => !str_starts_with((string)$k, '_'), ARRAY_FILTER_USE_KEY),
        ];
        $history   = $datos['_step_history'] ?? [];
        $history[] = $snapshot;
        if (count($history) > 15) {
            $history = array_slice($history, -15);
        }
        $this->saveDato($session, '_step_history', $history);
    }

    private function popHistory(BotSession $session): ?array
    {
        $datos   = $session->datos_parciales ?? [];
        $history = $datos['_step_history'] ?? [];
        if (empty($history)) return null;
        $snapshot = array_pop($history);
        $this->saveDato($session, '_step_history', $history);
        return $snapshot;
    }

    private function handleBack(BotSession $session): array
    {
        $estado = $session->estado_actual;

        if ($estado === 'COMPLETADO') {
            // Deportes no genera reserva — volver al menú directamente
            if ($session->rama_activa === 'DEPORTES') {
                $session->mergeEstado([
                    'estado_actual'      => 'MENU_PRINCIPAL',
                    'rama_activa'        => null,
                    'datos_parciales'    => [],
                    'contador_invalidos' => 0,
                ]);
                $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? 'cliente';
                return [BotMessages::render('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $nombre])];
            }
            return [BotMessages::render('MSG_VOLVER_CONFIRMADA')];
        }

        if ($estado === 'CAMBIANDO_DATO') {
            $this->saveDato($session, 'cambiando_paso', null);
            $session->mergeEstado(['estado_actual' => 'CONFIRMACION', 'contador_invalidos' => 0]);
            return [$this->buildConfirmacionMsg($session)];
        }

        $snapshot = $this->popHistory($session);

        if (!$snapshot) {
            $session->mergeEstado([
                'estado_actual'      => 'MENU_PRINCIPAL',
                'rama_activa'        => null,
                'subtipo_activo'     => null,
                'current_step'       => null,
                'datos_parciales'    => [],
                'contador_invalidos' => 0,
            ]);
            $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? 'cliente';
            return [BotMessages::render('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $nombre])];
        }

        // Restaurar snapshot y preservar historial actualizado
        $datos                  = $snapshot['datos'];
        $currentDatos           = $session->datos_parciales ?? [];
        $datos['_step_history'] = $currentDatos['_step_history'] ?? [];

        $session->mergeEstado([
            'estado_actual'      => $snapshot['estado_actual'],
            'rama_activa'        => $snapshot['rama_activa'],
            'subtipo_activo'     => $snapshot['subtipo_activo'],
            'current_step'       => $snapshot['current_step'],
            'datos_parciales'    => $datos,
            'contador_invalidos' => 0,
        ]);

        return $this->getMessageForStep($session);
    }

    private function getMessageForStep(BotSession $session): array
    {
        $rama    = $session->rama_activa;
        $subtipo = $session->subtipo_activo;
        $step    = $session->current_step;
        $datos   = $session->datos_parciales ?? [];

        if ($rama === 'EVENTOS' && $step === 'tipo_evento') {
            return [BotMessages::render('MSG_EVT_01')];
        }

        if ($rama === 'RESTAURANTE') {
            $msgMap = [
                'fecha'          => fn() => [BotMessages::render('MSG_RES_01')],
                'hora'           => fn() => [BotMessages::render('MSG_RES_02')],
                'numero_personas'=> fn() => [BotMessages::render('MSG_RES_03')],
                'mail_contacto'  => fn() => $this->getMailMessages($session, 'MSG_RES_06'),
            ];
            return ($msgMap[$step] ?? fn() => $this->escalate($session, 'SOLICITUD_CLIENTE'))();
        }

        if ($rama === 'EVENTOS') {
            if ($subtipo === 'NINOS') {
                if (str_starts_with((string)$step, 'adicional_qty_')) {
                    $itemId = (int)substr((string)$step, strlen('adicional_qty_'));
                    return [BotMessages::render('MSG_EVT_ADICIONAL_QTY', ['item_name' => BotMessages::adicionalLabel($itemId)])];
                }
                $msgMap = [
                    'pack_seleccionado'         => fn() => [BotMessages::render('MSG_EVT_NINOS_PACK')],
                    'fecha'                     => fn() => [BotMessages::render('MSG_EVT_02')],
                    'hora_inicio'               => fn() => [BotMessages::render('MSG_EVT_03_ENTERO')],
                    'numero_ninos'              => fn() => [BotMessages::render('MSG_EVT_05')],
                    'menu_preferido'            => fn() => [BotMessages::render('MSG_EVT_MENU')],
                    'numero_adultos'            => fn() => [BotMessages::render('MSG_EVT_ADULTOS', [
                        'precio_menu_adulto' => number_format(CostoEvento::precio('menu_adulto'), 0, ',', '.'),
                    ])],
                    'menu_adultos'              => fn() => [BotMessages::render('MSG_EVT_MENU_ADULTOS', [
                        'numero_adultos' => $datos['numero_adultos'] ?? '?',
                    ])],
                    'alimentos_adicionales'     => fn() => [BotMessages::render('MSG_EVT_ADICIONALES')],
                    'extras_texto'              => fn() => [BotMessages::render('MSG_EVT_EXTRAS')],
                    'mail_contacto'             => fn() => $this->getMailMessages($session, 'MSG_EVT_MAIL'),
                    'nombre_responsable'        => fn() => [BotMessages::render('MSG_EVT_07')],
                    'nombre_responsable_custom' => fn() => [BotMessages::render('MSG_EVT_07_CUSTOM')],
                ];
                return ($msgMap[$step] ?? fn() => $this->escalate($session, 'SOLICITUD_CLIENTE'))();
            }

            $msgMap = [
                'fecha'                     => fn() => [BotMessages::render('MSG_EVT_02')],
                'hora_inicio'               => fn() => [BotMessages::render('MSG_EVT_03_HHMM')],
                'numero_personas'           => fn() => [BotMessages::render('MSG_EVT_PERSONAS')],
                'nombre_responsable'        => fn() => [BotMessages::render('MSG_EVT_07')],
                'nombre_responsable_custom' => fn() => [BotMessages::render('MSG_EVT_07_CUSTOM')],
                'mail_contacto'             => fn() => $this->getMailMessages($session, 'MSG_EVT_MAIL'),
            ];
            return ($msgMap[$step] ?? fn() => $this->escalate($session, 'SOLICITUD_CLIENTE'))();
        }

        $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? 'cliente';
        return [BotMessages::render('MSG_BIENVENIDA_CONOCIDO', ['nombre' => $nombre])];
    }

    private function getMailMessages(BotSession $session, string $fallbackMsgId): array
    {
        $datos        = $session->datos_parciales ?? [];
        $mailConocido = $datos['_mail_conocido'] ?? null;
        if (!$mailConocido) {
            $mailConocido = Cliente::find($session->id_cliente)?->mail_contacto;
            if ($mailConocido) {
                $this->saveDato($session, '_mail_conocido', $mailConocido);
            }
        }
        if ($mailConocido) {
            return [BotMessages::render('MSG_CONFIRMAR_MAIL', ['mail' => $mailConocido])];
        }
        return [BotMessages::render($fallbackMsgId)];
    }

    /**
     * Parsea formatos de fecha flexibles y devuelve Carbon si es válida y futura.
     * Acepta: 25/02/26, 25-02-26, 25/02, 25-02
     */
    private function parseEventDate(string $raw): ?Carbon
    {
        $text = trim(str_replace(['-', '.'], '/', $raw));

        // dd/mm/yy
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $text, $m)) {
            try {
                return Carbon::createFromFormat(
                    'd/m/y',
                    str_pad($m[1], 2, '0', STR_PAD_LEFT) . '/' .
                    str_pad($m[2], 2, '0', STR_PAD_LEFT) . '/' .
                    $m[3]
                )->startOfDay();
            } catch (\Throwable) { return null; }
        }

        // dd/mm — infiere año: usa el corriente si es futuro, el siguiente si ya pasó
        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $text, $m)) {
            try {
                $date = Carbon::createFromDate(now()->year, (int)$m[2], (int)$m[1])->startOfDay();
                if (!$date->isAfter(Carbon::today())) {
                    $date->addYear();
                }
                return $date;
            } catch (\Throwable) { return null; }
        }

        return null;
    }

    /**
     * Parsea formatos de hora flexibles y devuelve 'HH:MM' o null.
     * Acepta: 20:00, 20.00, 20hs, 20h, 8pm, 8am, 20
     */
    private function parseEventTime(string $raw): ?string
    {
        $text = trim(strtolower($raw));

        // HH:MM o HH.MM
        if (preg_match('/^(\d{1,2})[:.](\d{2})$/', $text, $m)) {
            $h = (int)$m[1]; $min = (int)$m[2];
            if ($h > 23 || $min > 59) return null;
            return sprintf('%02d:%02d', $h, $min);
        }

        // Nhs o Nh (ej: 20hs, 9h)
        if (preg_match('/^(\d{1,2})\s*h(?:s)?$/', $text, $m)) {
            $h = (int)$m[1];
            if ($h > 23) return null;
            return sprintf('%02d:00', $h);
        }

        // Npm o Nam (ej: 8pm, 10am)
        if (preg_match('/^(\d{1,2})\s*(am|pm)$/', $text, $m)) {
            $h = (int)$m[1];
            if ($m[2] === 'pm' && $h !== 12) $h += 12;
            if ($m[2] === 'am' && $h === 12) $h = 0;
            if ($h > 23) return null;
            return sprintf('%02d:00', $h);
        }

        // Número entero (ej: 20)
        if (preg_match('/^\d{1,2}$/', $text)) {
            $h = (int)$text;
            if ($h < 8 || $h > 23) return null;
            return sprintf('%02d:00', $h);
        }

        return null;
    }
}
