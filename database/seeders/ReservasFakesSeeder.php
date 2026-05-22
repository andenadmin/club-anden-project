<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReservasFakesSeeder extends Seeder
{
    public function run(): void
    {
        $hoy = Carbon::today();

        $nombres = [
            'Sofía Ramírez','Martín Gutiérrez','Luciana Fernández','Nicolás Álvarez',
            'Valentina Torres','Diego Morales','Camila Sánchez','Joaquín Herrera',
            'Florencia Castro','Ezequiel Romero','Ana Belén Ruiz','Pablo Méndez',
            'Agustina López','Federico Peralta','Carolina Vega','Sebastián Núñez',
            'Mariana Ibáñez','Gonzalo Ríos','Julieta Suárez','Rodrigo Acosta',
            'Natalia Benitez','Tomás Giménez','Daniela Ortiz','Leandro Vargas',
            'Micaela Salinas',
        ];

        // Crear un cliente por nombre
        $clientes = [];
        foreach ($nombres as $i => $nombre) {
            $tel = '549' . str_pad((string)(11_000_000 + $i), 10, '0', STR_PAD_LEFT);
            $clientes[] = Cliente::firstOrCreate(
                ['numero_contacto' => $tel],
                ['nombre_cliente'  => $nombre]
            );
        }

        $horas        = ['11:30','14:00','20:00','22:00'];
        $personasOpts = ['1 a 2 personas','3 a 4 personas','5 a 6 personas','7 a 8 personas','9 a 14 personas'];
        $extras       = [
            null, null, null,
            'Silla alta para bebé por favor',
            'Cumpleaños, si pueden preparar algo especial',
            'Una persona celíaca, necesitamos menú sin gluten',
            'Aniversario, preferimos mesa exterior',
            'Alergia al maní',
            'Festejo de fin de año del trabajo',
        ];
        $packsNinos   = ['A','B','C'];
        $tiposEvento  = ['Evento Adultos','Cumpleaños Adolescentes','Cumpleaños Adultos'];

        $totalPorDia = 25;
        $cli = 0; // índice rotativo de clientes

        for ($dia = 1; $dia <= 15; $dia++) {
            $fechaCarbon = $hoy->copy()->addDays($dia);
            $fechaStr    = $fechaCarbon->format('d/m/y');
            $esFutura    = $dia > 7;

            for ($n = 0; $n < $totalPorDia; $n++) {
                $cliente = $clientes[$cli % count($clientes)];
                $cli++;

                // Distribuir: 15 restaurante, 5 ninos, 5 general_evt por día
                $tipo = match(true) {
                    $n < 15 => 'RESTAURANTE',
                    $n < 20 => 'NINOS',
                    default  => 'GENERAL_EVT',
                };

                if ($tipo === 'RESTAURANTE') {
                    $hora     = $horas[$n % count($horas)];
                    $personas = $personasOpts[$n % count($personasOpts)];
                    $extra    = $extras[$n % count($extras)];

                    Reserva::create([
                        'id_cliente'        => $cliente->id,
                        'rama_servicio'     => 'RESTAURANTE',
                        'subtipo'           => null,
                        'estado_reserva'    => $esFutura ? 'PENDIENTE_CONFIRMACION' : 'CONFIRMADA',
                        'tiene_extras'      => !is_null($extra),
                        'presupuesto_total' => null,
                        'datos'             => [
                            'fecha'              => $fechaStr,
                            'hora'               => $hora,
                            'numero_personas'    => $personas,
                            'nombre_responsable' => $cliente->nombre_cliente,
                            'mail_contacto'      => null,
                            'extras_texto'       => $extra,
                            'tiene_extras'       => !is_null($extra),
                            'fecha_es_futura'    => $esFutura,
                        ],
                    ]);
                } elseif ($tipo === 'NINOS') {
                    $idx     = $n - 15;
                    $horaInt = [10, 14, 15, 10, 14][$idx];
                    $ninos   = [15, 18, 22, 25, 30][$idx];
                    $adultos = [5, 6, 8, 8, 10][$idx];
                    $pack    = $packsNinos[$idx % count($packsNinos)];

                    Reserva::create([
                        'id_cliente'        => $cliente->id,
                        'rama_servicio'     => 'EVENTOS',
                        'subtipo'           => 'NINOS',
                        'estado_reserva'    => 'PENDIENTE_CONFIRMACION',
                        'tiene_extras'      => false,
                        'presupuesto_total' => rand(80_000, 220_000),
                        'datos'             => [
                            'fecha'              => $fechaStr,
                            'hora_inicio'        => $horaInt,
                            'numero_ninos'       => $ninos,
                            'numero_adultos'     => $adultos,
                            'pack_seleccionado'  => $pack,
                            'tipo_evento'        => 'Cumpleaños de Niños',
                            'nombre_responsable' => $cliente->nombre_cliente,
                            'mail_contacto'      => null,
                            'extras_texto'       => null,
                            'tiene_extras'       => false,
                            'es_feriado'         => 2,
                            'menu_preferido'     => 'Pizza',
                            'menu_adultos'       => 0,
                        ],
                    ]);
                } else {
                    $idx      = $n - 20;
                    $horaEvt  = ['19:00','20:00','19:30','20:00','19:00'][$idx];
                    $personas = [25, 40, 60, 35, 80][$idx];
                    $tipoEvt  = $tiposEvento[$idx % count($tiposEvento)];

                    Reserva::create([
                        'id_cliente'        => $cliente->id,
                        'rama_servicio'     => 'EVENTOS',
                        'subtipo'           => 'GENERAL_EVT',
                        'estado_reserva'    => 'PENDIENTE_CONFIRMACION',
                        'tiene_extras'      => false,
                        'presupuesto_total' => null,
                        'datos'             => [
                            'fecha'              => $fechaStr,
                            'hora_inicio'        => $horaEvt,
                            'numero_personas'    => $personas,
                            'tipo_evento'        => $tipoEvt,
                            'nombre_responsable' => $cliente->nombre_cliente,
                            'mail_contacto'      => null,
                            'extras_texto'       => null,
                            'tiene_extras'       => false,
                            'es_feriado'         => 2,
                        ],
                    ]);
                }
            }
        }
    }
}
