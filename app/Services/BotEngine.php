<?php

namespace App\Services;

use App\Models\BotSession;
use App\Models\Cliente;
use App\Models\CostoEvento;
use App\Models\Feriado;
use App\Models\Reserva;
use Carbon\Carbon;

class BotEngine
{
    public function process(string $from, string $text): array
    {
        $text    = trim($text);
        $session = BotSession::firstOrCreate(
            ['numero_contacto' => $from],
            ['estado_actual' => 'INICIO', 'datos_parciales' => [], 'contador_invalidos' => 0]
        );

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
            $session->mergeEstado(['estado_actual' => 'COMPLETADO', 'contador_invalidos' => 0]);
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
                return $this->nextStep($session, 'sector', 'MSG_RES_04');

            case 'sector':
                $sector = BotMessages::resolveOption('MSG_RES_04', $text);
                if (!$sector) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_04')]);
                }
                $this->saveDato($session, 'sector', $sector);
                return $this->nextStep($session, 'nombre_responsable', 'MSG_RES_05');

            case 'nombre_responsable':
                $opts05  = BotMessages::parseOptions('MSG_RES_05');
                $keysRes = array_values(array_filter(array_map('strval', array_keys($opts05)), fn ($k) => $k !== '0'));
                $upper   = strtoupper(trim($text));
                if (!in_array($upper, array_map('strval', array_keys($opts05)), true)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_05')]);
                }
                if ($upper === ($keysRes[0] ?? '1')) {
                    $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? '';
                    $this->saveDato($session, 'nombre_responsable', $nombre);
                    return $this->nextStep($session, 'mail_contacto', 'MSG_RES_06');
                }
                $session->mergeEstado(['current_step' => 'nombre_responsable_custom', 'contador_invalidos' => 0]);
                return [BotMessages::render('MSG_RES_05_CUSTOM')];

            case 'nombre_responsable_custom':
                $this->saveDato($session, 'nombre_responsable', $text);
                return $this->nextStep($session, 'mail_contacto', 'MSG_RES_06');

            case 'mail_contacto':
                if (!$this->isValidEmail($text)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_MAIL_INVALIDO')]);
                }
                $this->saveDato($session, 'mail_contacto', $text);
                return $this->goToConfirmacion($session);

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
                if (!preg_match('/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/\d{2}$/', $text)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_02')]);
                }
                $esFeriadoNinos = Feriado::esFeriado($text);
                $this->saveDato($session, 'fecha', $text);
                $this->saveDato($session, 'es_feriado', $esFeriadoNinos ? 1 : 2);
                $session->mergeEstado(['current_step' => 'hora_inicio', 'contador_invalidos' => 0]);
                $msgs = [];
                if ($esFeriadoNinos) $msgs[] = BotMessages::render('MSG_EVT_FERIADO_AVISO');
                $msgs[] = BotMessages::render('MSG_EVT_03_ENTERO');
                return $msgs;

            case 'hora_inicio':
                if (!ctype_digit($text) || (int)$text < 8 || (int)$text > 23) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_03_ENTERO')]);
                }
                $this->saveDato($session, 'hora_inicio', (int)$text);
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
                return $this->nextStep($session, 'mail_contacto', 'MSG_EVT_MAIL');

            case 'mail_contacto':
                $lower = strtolower($text);
                if ($lower !== 'no' && !$this->isValidEmail($text)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_MAIL')]);
                }
                $this->saveDato($session, 'mail_contacto', $lower === 'no' ? null : $text);
                return $this->nextStep($session, 'nombre_responsable', 'MSG_EVT_07');

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
                if (!preg_match('/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/\d{2}$/', $text)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_02')]);
                }
                $esFeriadoGen = Feriado::esFeriado($text);
                $this->saveDato($session, 'fecha', $text);
                $this->saveDato($session, 'es_feriado', $esFeriadoGen ? 1 : 2);
                $session->mergeEstado(['current_step' => 'hora_inicio', 'contador_invalidos' => 0]);
                $msgsGen = [];
                if ($esFeriadoGen) $msgsGen[] = BotMessages::render('MSG_EVT_FERIADO_AVISO');
                $msgsGen[] = BotMessages::render('MSG_EVT_03_HHMM');
                return $msgsGen;

            case 'hora_inicio':
                if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $text)) {
                    return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_EVT_03_HHMM')]);
                }
                $this->saveDato($session, 'hora_inicio', $text);
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
                    return $this->goToConfirmacion($session);
                }
                $session->mergeEstado(['current_step' => 'nombre_responsable_custom', 'contador_invalidos' => 0]);
                return [BotMessages::render('MSG_EVT_07_CUSTOM')];

            case 'nombre_responsable_custom':
                $this->saveDato($session, 'nombre_responsable', $text);
                return $this->goToConfirmacion($session);

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

        // 2da opción = cambiar (solo RESTAURANTE)
        if (isset($keys[1]) && $upper === $keys[1]) {
            if ($session->rama_activa !== 'RESTAURANTE') {
                return $this->handleInvalid($session, fn () => [$this->buildConfirmacionMsg($session)]);
            }
            $this->saveDato($session, 'cambiando_paso', null);
            $session->mergeEstado(['estado_actual' => 'CAMBIANDO_DATO', 'contador_invalidos' => 0]);
            return [BotMessages::render('MSG_RES_CAMBIAR')];
        }

        return $this->handleInvalid($session, fn () => [$this->buildConfirmacionMsg($session)]);
    }

    private function confirmarReserva(BotSession $session): array
    {
        $datos    = $session->datos_parciales ?? [];
        $rama     = $session->rama_activa;
        $esFutura = $datos['fecha_es_futura'] ?? false;

        $estado = match(true) {
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

        // Incrementar contador
        $counter = match($rama) {
            'RESTAURANTE' => 'contador_reservas_restaurante',
            'EVENTOS'     => 'contador_reservas_eventos',
            default       => null,
        };
        if ($counter) Cliente::find($session->id_cliente)?->increment($counter);

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

    // ─── CAMBIAR DATO (solo RESTAURANTE) ──────────────────────────────────────

    private function handleCambiandoDato(BotSession $session, string $text): array
    {
        $datos = $session->datos_parciales ?? [];
        $cambiandoPaso = $datos['cambiando_paso'] ?? null;

        if ($cambiandoPaso === null) {
            // Esperando selección de campo — dynamic desde MSG_RES_CAMBIAR
            $opts      = BotMessages::parseOptions('MSG_RES_CAMBIAR');
            $strKeys   = array_map('strval', array_keys($opts));
            $nonZero   = array_values(array_filter($strKeys, fn ($k) => $k !== '0'));
            $upperText = strtoupper(trim($text));
            if (!in_array($upperText, $strKeys, true)) {
                return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_CAMBIAR')]);
            }
            $stepSequence = ['fecha', 'hora', 'numero_personas', 'sector', 'nombre_responsable', 'mail_contacto'];
            $pos  = array_search($upperText, $nonZero, true);
            $paso = $pos !== false ? ($stepSequence[$pos] ?? null) : null;
            if ($paso === null) {
                return $this->handleInvalid($session, fn () => [BotMessages::render('MSG_RES_CAMBIAR')]);
            }
            $this->saveDato($session, 'cambiando_paso', $paso);
            $session->mergeEstado(['contador_invalidos' => 0]);
            // Reenviar mensaje del paso
            $msgMap = [
                'fecha'              => 'MSG_RES_01',
                'hora'               => 'MSG_RES_02',
                'numero_personas'    => 'MSG_RES_03',
                'sector'             => 'MSG_RES_04',
                'nombre_responsable' => 'MSG_RES_05',
                'mail_contacto'      => 'MSG_RES_06',
            ];
            return [BotMessages::render($msgMap[$paso])];
        }

        // Recibiendo nuevo valor para el campo
        $result = $this->validateAndSaveCambio($session, $cambiandoPaso, $text);
        if ($result === false) {
            // Inválido
            $msgMap = [
                'fecha'              => 'MSG_RES_01',
                'hora'               => 'MSG_RES_02',
                'numero_personas'    => 'MSG_RES_03',
                'sector'             => 'MSG_RES_04',
                'nombre_responsable' => 'MSG_RES_05',
                'nombre_responsable_custom' => 'MSG_RES_05_CUSTOM',
                'mail_contacto'      => 'MSG_RES_06',
            ];
            return $this->handleInvalid($session, fn () => [BotMessages::render($msgMap[$cambiandoPaso] ?? 'MSG_RES_01')]);
        }
        if ($result === 'ask_custom_nombre') {
            $this->saveDato($session, 'cambiando_paso', 'nombre_responsable_custom');
            $session->mergeEstado(['contador_invalidos' => 0]);
            return [BotMessages::render('MSG_RES_05_CUSTOM')];
        }

        // Guardado OK — volver a confirmación
        $this->saveDato($session, 'cambiando_paso', null);
        $session->mergeEstado(['estado_actual' => 'CONFIRMACION', 'contador_invalidos' => 0]);
        return [$this->buildConfirmacionMsg($session)];
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
            case 'sector':
                $sector = BotMessages::resolveOption('MSG_RES_04', $text);
                if (!$sector) return false;
                $this->saveDato($session, 'sector', $sector);
                return true;
            case 'nombre_responsable':
                $optsC = BotMessages::parseOptions('MSG_RES_05');
                $keysC = array_values(array_filter(array_map('strval', array_keys($optsC)), fn ($k) => $k !== '0'));
                $upperC = strtoupper(trim($text));
                if (!in_array($upperC, array_map('strval', array_keys($optsC)), true)) return false;
                if ($upperC === ($keysC[0] ?? '1')) {
                    $nombre = Cliente::find($session->id_cliente)?->nombre_cliente ?? '';
                    $this->saveDato($session, 'nombre_responsable', $nombre);
                    return true;
                }
                return 'ask_custom_nombre';
            case 'nombre_responsable_custom':
                $this->saveDato($session, 'nombre_responsable', $text);
                return true;
            case 'mail_contacto':
                if (!$this->isValidEmail($text)) return false;
                $this->saveDato($session, 'mail_contacto', $text);
                return true;
            default:
                return false;
        }
    }

    // ─── ESCALADO HUMANO ──────────────────────────────────────────────────────

    private function escalate(BotSession $session, string $motivo): array
    {
        $session->mergeEstado([
            'estado_actual'   => 'PAUSADO',
            'timestamp_pausa' => Carbon::now(),
            'contador_invalidos' => 0,
        ]);
        // TODO: emitir alerta interna con datos del escalado
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
            if (!empty($datos['sector']))            $lines[] = "📍 Sector: {$datos['sector']}";
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

    private function isValidEmail(string $email): bool
    {
        return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email);
    }
}
