<?php

namespace App\Console\Commands;

use App\Models\BotSession;
use App\Models\ConversationMessage;
use App\Services\BotEngine;
use App\Services\BotMessages;
use App\Services\Meta\WhatsAppSender;
use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Throwable;

class SweepAdvisorTakeoverCommand extends Command
{
    protected $signature = 'inbox:sweep-takeovers
                            {--cap-hours=12 : Horas a partir de las cuales se fuerza el reset.}';

    protected $description = 'Aplica el cap automático de 12h sobre conversaciones con motivo_pausa = ASESOR_TAKEOVER. Resetea a INICIO y manda MSG_TIMEOUT_ASESOR.';

    public function handle(BotEngine $engine, WhatsAppSender $sender): int
    {
        $capHours = (int) $this->option('cap-hours');
        $cutoff   = now()->subHours($capHours);

        $sessions = BotSession::query()
            ->where('motivo_pausa', 'ASESOR_TAKEOVER')
            ->where('timestamp_pausa', '<=', $cutoff)
            ->get();

        if ($sessions->isEmpty()) {
            $this->info("Sin conversaciones que excedan el cap de {$capHours}h.");
            return self::SUCCESS;
        }

        $this->info("Procesando {$sessions->count()} conversación/es vencida/s…");

        foreach ($sessions as $session) {
            try {
                $waited = CarbonInterval::seconds($cutoff->diffInSeconds($session->timestamp_pausa))
                    ->cascade()
                    ->forHumans(['parts' => 2, 'short' => true]);

                $session->mergeEstado([
                    'estado_actual'          => 'INICIO',
                    'estado_previo_pausa'    => null,
                    'rama_activa'            => null,
                    'subtipo_activo'         => null,
                    'current_step'           => null,
                    'motivo_pausa'           => null,
                    'timestamp_pausa'        => null,
                    'next_resume_check_at'   => null,
                    'resolved_by_advisor_at' => null,
                    'datos_parciales'        => [],
                    'contador_invalidos'     => 0,
                    'unread_count'           => 0,
                ]);

                // Mensaje de disculpas al usuario
                $body = BotMessages::render('MSG_TIMEOUT_ASESOR');
                $sender->sendBotResponses($session, [$body]);

                $this->line("  · {$session->numero_contacto}: reseteado tras {$waited} sin atender.");
            } catch (Throwable $e) {
                report($e);
                $this->error("  · {$session->numero_contacto}: error procesando — {$e->getMessage()}");
            }
        }

        // ── Segunda pasada: SOLICITUD_CLIENTE / OPCIONES_INVALIDAS_REITERADAS / CAPACIDAD_EXCEDIDA sin atender ──

        $unattendedSessions = BotSession::query()
            ->where('estado_actual', 'PAUSADO')
            ->whereIn('motivo_pausa', ['SOLICITUD_CLIENTE', 'OPCIONES_INVALIDAS_REITERADAS', 'CAPACIDAD_EXCEDIDA'])
            ->where('timestamp_pausa', '<=', $cutoff)
            ->get()
            ->filter(function (BotSession $session) {
                // Solo si ningún asesor respondió desde que se pausó
                return !ConversationMessage::where('bot_session_id', $session->id)
                    ->where('sender', ConversationMessage::SENDER_ADVISOR)
                    ->where('created_at', '>=', $session->timestamp_pausa)
                    ->exists();
            });

        foreach ($unattendedSessions as $session) {
            try {
                $body = BotMessages::render('MSG_TIMEOUT_ASESOR');
                $sender->sendBotResponses($session, [$body]);

                $session->mergeEstado([
                    'estado_actual'        => 'INICIO',
                    'estado_previo_pausa'  => null,
                    'motivo_pausa'         => 'SOLICITUD_NO_ATENDIDA',
                    'timestamp_pausa'      => null,
                    'next_resume_check_at' => null,
                    'datos_parciales'      => [],
                    'contador_invalidos'   => 0,
                    'unread_count'         => 1,
                ]);

                $this->line("  · {$session->numero_contacto}: reanudado automáticamente por falta de atención.");
            } catch (Throwable $e) {
                report($e);
                $this->error("  · {$session->numero_contacto}: error procesando — {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
