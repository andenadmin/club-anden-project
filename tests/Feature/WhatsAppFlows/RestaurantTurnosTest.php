<?php

namespace Tests\Feature\WhatsAppFlows;

use App\Models\Cliente;
use App\Models\Feriado;
use App\Models\Reserva;
use App\Models\RestaurantCapacityOverride;
use App\Models\RestaurantConfig;
use App\Services\BotMessages;
use App\Services\RestaurantTurnos;
use App\Services\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RestaurantTurnosTest extends TestCase
{
    use RefreshDatabase;

    // Lunes 5/10/2026, 09:00. El lunes 12/10 es día de semana dentro del rango de 7 días.
    private const AHORA = '2026-10-05 09:00:00';
    private const LUNES = '2026-10-12';

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::AHORA);
        RestaurantConfig::clearCache();
        BotMessages::clearCache();

        DB::table('restaurant_config')->update([
            'salon_capacidad'    => 52,
            'galeria_capacidad'  => 69,
            'terraza_capacidad'  => 20,
            'parrilla_capacidad' => 20,
            'patio_capacidad'    => 79,
            'capacidad_pct'      => 70,
        ]);

        $this->cliente = Cliente::create(['numero_contacto' => '5491100000001']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestaurantConfig::clearCache();
        parent::tearDown();
    }

    private function turnos(string $ahora = self::AHORA): RestaurantTurnos
    {
        return new RestaurantTurnos(Carbon::parse($ahora));
    }

    private function reservar(string $fecha, string $hora, int $personas, ?string $sector, string $estado = 'CONFIRMADA'): void
    {
        Reserva::create([
            'id_cliente'     => $this->cliente->id,
            'rama_servicio'  => 'RESTAURANTE',
            'estado_reserva' => $estado,
            'datos'          => [
                'fecha'           => Carbon::parse($fecha)->format('d/m/y'),
                'hora'            => $hora,
                'numero_personas' => "{$personas} personas",
                'sector_key'      => $sector,
            ],
        ]);
    }

    /** @return string[] ids de sectores disponibles */
    private function sectores(string $hora, int $personas, string $fecha = self::LUNES, ?RestaurantTurnos $t = null): array
    {
        $t     ??= $this->turnos();
        $dia     = Carbon::parse($fecha);
        $turno   = $t->buscarTurno($dia, $hora);
        $this->assertNotNull($turno, "No existe el turno {$hora}");

        return array_column($t->sectoresDisponibles($dia, $turno, $personas), 'id');
    }

    public function test_interpreta_los_turnos_del_panel_con_su_hora_de_fin(): void
    {
        $turnos = $this->turnos()->turnosDelDia(Carbon::parse(self::LUNES));
        $rangos = array_map(fn (Turno $t) => [$t->hora, $t->hasta], $turnos);

        $this->assertSame([
            ['11:45', 14 * 60],
            ['14:00', null],
            ['12:00', 16 * 60],
            ['20:00', null],
            ['22:00', null],
        ], $rangos);
    }

    public function test_fin_de_semana_solo_ofrece_12_y_14(): void
    {
        $horas = array_map(fn (Turno $t) => $t->hora, $this->turnos()->turnosDelDia(Carbon::parse('2026-10-10')));

        $this->assertSame(['12:00', '14:00'], $horas);
    }

    public function test_feriado_de_semana_usa_los_turnos_de_fin_de_semana(): void
    {
        Feriado::create(['fecha' => '2026-10-12', 'nombre' => 'Día de la Diversidad Cultural']);

        $horas = array_map(fn (Turno $t) => $t->hora, $this->turnos()->turnosDelDia(Carbon::parse(self::LUNES)));

        $this->assertSame(['12:00', '14:00'], $horas);
    }

    public function test_cada_sector_se_llena_al_70_por_ciento(): void
    {
        // Salón 52 × 70% = 36
        $this->reservar(self::LUNES, '20:00', 30, 'salon');

        $this->assertContains('salon', $this->sectores('20:00', 6));
        $this->assertNotContains('salon', $this->sectores('20:00', 7));
    }

    public function test_el_turno_de_12_descuenta_del_cupo_de_11_45_y_de_14(): void
    {
        $this->reservar(self::LUNES, '12:00', 30, 'salon');

        $this->assertNotContains('salon', $this->sectores('11:45', 7));
        $this->assertNotContains('salon', $this->sectores('14:00', 7));
        $this->assertContains('salon', $this->sectores('11:45', 6));
        $this->assertContains('salon', $this->sectores('20:00', 36));
    }

    public function test_reservar_a_las_12_mira_el_pico_entre_11_45_y_14(): void
    {
        $this->reservar(self::LUNES, '11:45', 20, 'salon');
        $this->reservar(self::LUNES, '14:00', 10, 'salon');

        // Pico a las 12:00 = 20 (el de 11:45 sigue), a las 14:00 = 10 → entran 16 más.
        $this->assertContains('salon', $this->sectores('12:00', 16));
        $this->assertNotContains('salon', $this->sectores('12:00', 17));
    }

    public function test_11_45_y_14_no_se_superponen_entre_si(): void
    {
        $this->reservar(self::LUNES, '11:45', 36, 'salon');

        $this->assertNotContains('salon', $this->sectores('11:45', 1));
        $this->assertContains('salon', $this->sectores('14:00', 36));
    }

    public function test_las_reservas_canceladas_no_ocupan_y_las_pendientes_si(): void
    {
        $this->reservar(self::LUNES, '20:00', 36, 'salon', 'CANCELADA');
        $this->assertContains('salon', $this->sectores('20:00', 36));

        $this->reservar(self::LUNES, '20:00', 36, 'salon', 'PENDIENTE_CONFIRMACION');
        $this->assertNotContains('salon', $this->sectores('20:00', 1));
    }

    public function test_sin_preferencia_aparece_si_algun_sector_tiene_lugar(): void
    {
        $this->assertContains(RestaurantTurnos::SIN_PREFERENCIA, $this->sectores('20:00', 4));
    }

    public function test_sin_preferencia_no_aparece_si_todos_los_sectores_estan_llenos(): void
    {
        foreach (['salon' => 36, 'galeria' => 48, 'terraza' => 14, 'parrilla' => 14, 'patio' => 55] as $sector => $n) {
            $this->reservar(self::LUNES, '20:00', $n, $sector);
        }

        $this->assertSame([], $this->sectores('20:00', 1));
    }

    public function test_sin_preferencia_controla_la_capacidad_maxima_del_restaurante(): void
    {
        // Capacidad máxima (100%) = 52+69+20+20+79 = 240. Reservas sin sector: 235.
        $this->reservar(self::LUNES, '20:00', 235, null);

        $disponibles = $this->sectores('20:00', 6);

        $this->assertContains('salon', $disponibles, 'Los sectores individuales no cuentan las reservas sin preferencia');
        $this->assertNotContains(RestaurantTurnos::SIN_PREFERENCIA, $disponibles);
        $this->assertContains(RestaurantTurnos::SIN_PREFERENCIA, $this->sectores('20:00', 5));
    }

    public function test_sector_cerrado_hoy_no_se_ofrece(): void
    {
        DB::table('restaurant_config')->update(['salon_cerrado' => true, 'sectores_cerrado_fecha' => '2026-10-05']);
        RestaurantConfig::clearCache();

        $this->assertNotContains('salon', $this->sectores('20:00', 2, '2026-10-05'));
        $this->assertContains('salon', $this->sectores('20:00', 2, '2026-10-06'));
    }

    public function test_respeta_el_override_de_capacidad_por_fecha(): void
    {
        RestaurantCapacityOverride::create(['fecha' => self::LUNES, 'salon_max' => 10]);

        $this->assertContains('salon', $this->sectores('20:00', 10));
        $this->assertNotContains('salon', $this->sectores('20:00', 11));
    }

    public function test_hoy_solo_ofrece_turnos_que_no_pasaron_y_saca_los_llenos(): void
    {
        foreach (['salon' => 36, 'galeria' => 48, 'terraza' => 14, 'parrilla' => 14, 'patio' => 55] as $sector => $n) {
            $this->reservar('2026-10-05', '22:00', $n, $sector);
        }

        $disp  = $this->turnos('2026-10-05 15:00:00')->disponibilidad(Carbon::parse('2026-10-05'), 2);
        $horas = array_map(fn ($d) => $d['turno']->hora, $disp);

        $this->assertSame(['20:00'], $horas);
    }

    public function test_motivos_para_no_tomar_la_reserva(): void
    {
        $t = $this->turnos('2026-10-10 10:59:00'); // sábado

        $this->assertNull($t->motivoSinReserva(Carbon::parse('2026-10-10'), 7));
        $this->assertSame('pasada', $t->motivoSinReserva(Carbon::parse('2026-10-09'), 7));
        $this->assertNull($t->motivoSinReserva(Carbon::parse('2026-10-17'), 7));
        $this->assertSame('fuera_de_rango', $t->motivoSinReserva(Carbon::parse('2026-10-18'), 7));

        $this->assertSame(
            'finde_orden_llegada',
            $this->turnos('2026-10-10 11:00:00')->motivoSinReserva(Carbon::parse('2026-10-10'), 7)
        );
        $this->assertNull(
            $this->turnos('2026-10-05 11:30:00')->motivoSinReserva(Carbon::parse('2026-10-05'), 7),
            'Un lunes común después de las 11 se sigue reservando'
        );
    }
}
