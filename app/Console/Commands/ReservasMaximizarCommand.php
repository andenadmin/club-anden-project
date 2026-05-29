<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\RestaurantConfig;
use App\Services\RestaurantCapacity;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReservasMaximizarCommand extends Command
{
    protected $signature = 'reservas:maximizar {fecha? : Fecha en formato DD/MM/AAAA (default: hoy)}
                            {--sector= : Solo rellenar este sector (salon|galeria|terraza|parrilla)}
                            {--personas=2 : Personas por reserva de prueba}';

    protected $description = 'Crea reservas de prueba hasta llenar todos los sectores en una fecha específica.';

    private const SECTORES = [
        'salon'    => 'Salón',
        'galeria'  => 'Galería',
        'terraza'  => 'Terraza',
        'parrilla' => 'Parrilla',
    ];

    private const HORAS = ['12:00', '13:00', '14:00', '15:00', '20:00', '21:00', '22:00', '23:00'];

    private const NOMBRES = [
        'Test Capacidad', 'Prueba Sistema', 'Demo Reserva', 'Test Bot', 'Prueba Lleno',
        'Test Máximo', 'Demo Ocupado', 'Reserva Prueba', 'Test Full', 'Demo Carga',
    ];

    public function handle(): int
    {
        $fechaArg = $this->argument('fecha');
        $personasPorReserva = (int) $this->option('personas');
        $soloSector         = $this->option('sector');

        try {
            $carbon = $fechaArg
                ? Carbon::createFromFormat('d/m/Y', $fechaArg)
                : Carbon::today();
        } catch (\Throwable) {
            $this->error("Formato de fecha inválido. Usá DD/MM/AAAA (ej: 28/05/2026).");
            return self::FAILURE;
        }

        $fechaBot  = $carbon->format('d/m/y');
        $fechaFmt  = $carbon->translatedFormat('l j \d\e F \d\e Y');
        $config    = RestaurantConfig::get();

        // Cliente de prueba reutilizable
        $cliente = Cliente::firstOrCreate(
            ['numero_contacto' => '5490000000000'],
            ['nombre_cliente'  => 'Test Sistema'],
        );

        $rows    = [];
        $total   = 0;

        $alertaPct = $config->sector_alerta_pct ?? 70;

        if ($soloSector && !array_key_exists($soloSector, self::SECTORES)) {
            $this->error("Sector inválido. Opciones: " . implode(', ', array_keys(self::SECTORES)));
            return self::FAILURE;
        }

        $sectoresAFill = $soloSector
            ? [$soloSector => self::SECTORES[$soloSector]]
            : self::SECTORES;

        foreach ($sectoresAFill as $key => $label) {
            $limite  = $config->limiteParaSector($key);
            $objetivo = (int) floor($limite * $alertaPct / 100);
            $usadas  = RestaurantCapacity::personasEnSector($key, $fechaBot);
            $hueco   = $objetivo - $usadas;

            if ($hueco <= 0) {
                $rows[] = [$label, "{$objetivo}/{$limite} ({$alertaPct}%)", $usadas, 0, '✅ Ya cubierto'];
                continue;
            }

            $creadas    = 0;
            $horaIndex  = 0;
            $nombreIdx  = 0;

            while ($hueco > 0) {
                $personas   = min($personasPorReserva, $hueco);
                $hora       = self::HORAS[$horaIndex % count(self::HORAS)];
                $nombre     = self::NOMBRES[$nombreIdx % count(self::NOMBRES)];

                Reserva::create([
                    'id_cliente'     => $cliente->id,
                    'rama_servicio'  => 'RESTAURANTE',
                    'subtipo'        => null,
                    'estado_reserva' => 'CONFIRMADA',
                    'datos'          => [
                        'fecha'              => $fechaBot,
                        'hora'               => $hora,
                        'sector_key'         => $key,
                        'numero_personas'    => (string) $personas,
                        'nombre_responsable' => $nombre,
                        'mail_contacto'      => '',
                        'extras_texto'       => '',
                        'tiene_extras'       => false,
                        'fecha_es_futura'    => false,
                        '_es_prueba'         => true,
                    ],
                    'tiene_extras'      => false,
                    'presupuesto_total' => null,
                ]);

                $hueco     -= $personas;
                $creadas   += $personas;
                $horaIndex++;
                $nombreIdx++;
                $total++;
            }

            $rows[] = [$label, "{$objetivo}/{$limite} ({$alertaPct}%)", $usadas, $creadas, '✅ Alerta disparada'];
        }

        $this->info("Reservas de prueba creadas para el {$fechaFmt} (objetivo: {$alertaPct}% de capacidad):");
        $this->table(
            ['Sector', 'Objetivo/Límite', 'Ya tenía', 'Personas agregadas', 'Estado'],
            $rows,
        );
        $totalPersonas = array_sum(array_column($rows, 3));
        $this->line("  <comment>{$total}</comment> reservas creadas · <comment>{$totalPersonas}</comment> personas en total");
        $this->line("Para borrarlas: <comment>php artisan reservas:limpiar --confirmar</comment>");

        // Disparar las alertas de capacidad para que aparezcan los dialogs en el panel
        foreach (array_keys($sectoresAFill) as $key) {
            RestaurantCapacity::checkAlertaOcupacion($key, $fechaBot);
        }

        return self::SUCCESS;
    }
}
