<?php

namespace App\Console\Commands;

use App\Models\Feriado;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncFeriados extends Command
{
    protected $signature   = 'feriados:sync {año? : Año a sincronizar (default: año actual)}';
    protected $description = 'Sincroniza los feriados nacionales argentinos desde argentinadatos.com';

    public function handle(): int
    {
        $año = (int) ($this->argument('año') ?? now()->year);

        $this->info("Sincronizando feriados para {$año}...");

        $response = Http::timeout(15)->get("https://api.argentinadatos.com/v1/feriados/{$año}");

        if ($response->failed()) {
            $this->error("Error al conectar con la API: HTTP {$response->status()}");
            return self::FAILURE;
        }

        $feriados = $response->json();

        if (!is_array($feriados) || empty($feriados)) {
            $this->warn("La API no devolvió feriados para {$año}.");
            return self::SUCCESS;
        }

        $rows = [];
        $now  = now()->toDateTimeString();

        foreach ($feriados as $item) {
            if (empty($item['fecha'])) continue;
            $rows[] = [
                'fecha'      => Carbon::parse($item['fecha'])->format('Y-m-d'),
                'nombre'     => $item['nombre'] ?? 'Feriado',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            $this->warn("No se encontraron feriados válidos en la respuesta.");
            return self::SUCCESS;
        }

        // upsert evita el problema del cast de fecha — opera directo sobre el string Y-m-d
        Feriado::upsert($rows, uniqueBy: ['fecha'], update: ['nombre', 'updated_at']);

        $this->info("Listo. " . count($rows) . " feriados sincronizados para {$año}.");

        return self::SUCCESS;
    }
}
