<?php

namespace App\Console\Commands;

use App\Models\BotSession;
use App\Models\Cliente;
use App\Models\ConversationMessage;
use App\Models\Reserva;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReservasLimpiarCommand extends Command
{
    protected $signature = 'reservas:limpiar
                            {--confirmar : Ejecuta el borrado (sin este flag es solo dry-run)}
                            {--todo      : También borra clientes, sesiones y mensajes de prueba}
                            {--numero=   : Borrar solo las reservas de un número de contacto específico}';

    protected $description = 'Borra reservas de prueba. Sin --confirmar solo muestra lo que se borraría.';

    public function handle(): int
    {
        $confirmar = $this->option('confirmar');
        $todo      = $this->option('todo');
        $numero    = $this->option('numero');

        $baseQuery = fn () => $numero
            ? Reserva::whereHas('cliente', fn ($q) => $q->where('numero_contacto', 'like', "%{$numero}%"))
            : Reserva::query();

        $reservas = $baseQuery()
            ->selectRaw('rama_servicio, estado_reserva, count(*) as total')
            ->groupBy('rama_servicio', 'estado_reserva')
            ->orderBy('rama_servicio')
            ->get();

        $totalReservas = $baseQuery()->count();

        if ($totalReservas === 0) {
            $this->info('No hay reservas' . ($numero ? " para el número {$numero}" : '') . '. Nada que borrar.');
            return self::SUCCESS;
        }

        // Resumen
        $this->line('');
        $this->warn($confirmar ? '⚠️  EJECUTANDO BORRADO REAL' : '🔍 DRY-RUN — nada se borrará (usá --confirmar para ejecutar)');
        $this->line('');

        $rows = $reservas->map(fn ($r) => [$r->rama_servicio, $r->estado_reserva, $r->total])->toArray();
        $this->table(['Rama', 'Estado', 'Cantidad'], $rows);
        $this->line("  Total reservas: <comment>{$totalReservas}</comment>");

        if ($todo) {
            $clientesCount  = $numero
                ? Cliente::where('numero_contacto', 'like', "%{$numero}%")->count()
                : Cliente::count();
            $sesionesCount  = $numero
                ? BotSession::whereHas('cliente', fn ($q) => $q->where('numero_contacto', 'like', "%{$numero}%"))->count()
                : BotSession::count();
            $mensajesCount  = $numero
                ? ConversationMessage::whereHas('botSession.cliente', fn ($q) => $q->where('numero_contacto', 'like', "%{$numero}%"))->count()
                : ConversationMessage::count();

            $this->line("  Clientes:       <comment>{$clientesCount}</comment>");
            $this->line("  Sesiones bot:   <comment>{$sesionesCount}</comment>");
            $this->line("  Mensajes:       <comment>{$mensajesCount}</comment>");
        }

        $this->line('');

        if (!$confirmar) {
            $this->line('Corré con <comment>--confirmar</comment> para borrar. Podés agregar <comment>--todo</comment> para borrar también clientes, sesiones y mensajes.');
            return self::SUCCESS;
        }

        if (!$this->confirm("¿Estás seguro? Esta acción no se puede deshacer.", false)) {
            $this->line('Cancelado.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($numero, $todo) {
            if ($todo) {
                if ($numero) {
                    $clienteIds = Cliente::where('numero_contacto', 'like', "%{$numero}%")->pluck('id');
                    $sessionIds = BotSession::whereIn('id_cliente', $clienteIds)->pluck('id');
                    ConversationMessage::whereIn('bot_session_id', $sessionIds)->delete();
                    BotSession::whereIn('id', $sessionIds)->delete();
                    Reserva::whereIn('id_cliente', $clienteIds)->delete();
                    Cliente::whereIn('id', $clienteIds)->delete();
                } else {
                    ConversationMessage::truncate();
                    BotSession::truncate();
                    Reserva::truncate();
                    Cliente::truncate();
                }
            } else {
                if ($numero) {
                    Reserva::whereHas('cliente', fn ($q) => $q->where('numero_contacto', 'like', "%{$numero}%"))->delete();
                } else {
                    Reserva::truncate();
                    // Resetear contadores de reservas en clientes
                    Cliente::query()->update([
                        'contador_reservas_deportes'    => 0,
                        'contador_reservas_restaurante' => 0,
                        'contador_reservas_eventos'     => 0,
                    ]);
                }
            }
        });

        $this->info('✅ Borrado completado.');
        return self::SUCCESS;
    }
}
