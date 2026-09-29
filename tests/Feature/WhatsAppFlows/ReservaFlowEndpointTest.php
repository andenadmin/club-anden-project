<?php

namespace Tests\Feature\WhatsAppFlows;

use App\Http\Controllers\WhatsAppFlowController;
use App\Models\BotSession;
use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\RestaurantConfig;
use App\Models\WhatsAppFlowToken;
use App\Services\BotMessages;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

class ReservaFlowEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const URI    = '/test/whatsapp-flows/reserva';
    private const SECRET = 'app-secret-de-prueba';
    private const LUNES  = '2026-10-12';

    private static RSA\PrivateKey $key;

    private BotSession $session;
    private WhatsAppFlowToken $token;
    private string $aesKey;
    private string $iv;

    public static function setUpBeforeClass(): void
    {
        self::$key = RSA::createKey(2048);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00'); // lunes
        RestaurantConfig::clearCache();
        BotMessages::clearCache();
        DB::table('restaurant_config')->update([
            'salon_capacidad' => 52, 'galeria_capacidad' => 69, 'terraza_capacidad' => 20,
            'parrilla_capacidad' => 20, 'patio_capacidad' => 79, 'capacidad_pct' => 70,
        ]);

        config([
            'services.whatsapp.app_secret' => self::SECRET,
            'whatsapp_flows.private_key'   => base64_encode(self::$key->toString('PKCS8')),
        ]);
        $this->app->forgetInstance(\App\Services\Meta\WhatsAppClient::class);

        Route::post(self::URI, [WhatsAppFlowController::class, 'reserva']);

        $this->session = BotSession::create([
            'numero_contacto' => '5491100000001', 'estado_actual' => 'MENU_PRINCIPAL',
            'datos_parciales' => [], 'contador_invalidos' => 0,
        ]);
        $this->token = WhatsAppFlowToken::emitir($this->session, WhatsAppFlowToken::FLOW_RESERVA, 24);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestaurantConfig::clearCache();
        parent::tearDown();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function cifrar(array $payload): string
    {
        $this->aesKey = random_bytes(16);
        $this->iv     = random_bytes(16);
        $tag = '';
        $ct  = openssl_encrypt(json_encode($payload), 'aes-128-gcm', $this->aesKey, OPENSSL_RAW_DATA, $this->iv, $tag, '', 16);
        $key = self::$key->getPublicKey()->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256')->encrypt($this->aesKey);

        return json_encode([
            'encrypted_flow_data' => base64_encode($ct . $tag),
            'encrypted_aes_key'   => base64_encode($key),
            'initial_vector'      => base64_encode($this->iv),
        ]);
    }

    private function enviar(string $body, ?string $firma = null): \Illuminate\Testing\TestResponse
    {
        $firma ??= 'sha256=' . hash_hmac('sha256', $body, self::SECRET);

        return $this->call('POST', self::URI, [], [], [], [
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256'  => $firma,
        ], $body);
    }

    private function descifrar(string $respuesta): array
    {
        $raw = base64_decode($respuesta);
        $plain = openssl_decrypt(substr($raw, 0, -16), 'aes-128-gcm', $this->aesKey, OPENSSL_RAW_DATA, ~$this->iv, substr($raw, -16));
        $this->assertNotFalse($plain, 'La respuesta no se pudo descifrar con el IV invertido');

        return json_decode($plain, true);
    }

    private function flow(string $action, array $data = [], ?string $token = null): array
    {
        $res = $this->enviar($this->cifrar([
            'version' => '3.0', 'action' => $action, 'screen' => 'X',
            'data' => $data, 'flow_token' => $token ?? $this->token->token,
        ]));
        $res->assertOk();
        $this->assertStringStartsWith('text/plain', $res->headers->get('Content-Type'));

        return $this->descifrar($res->getContent());
    }

    private function confirmar(array $extra = []): array
    {
        return $this->flow('data_exchange', array_merge([
            'accion' => 'confirmar', 'fecha' => self::LUNES, 'personas' => '4', 'turno' => '20:00',
            'sector' => 'galeria', 'nombre' => 'Martina', 'comentarios' => 'Es un cumple', 'acepta_tyc' => true,
        ], $extra));
    }

    private function ocupar(string $hora, array $sectores): void
    {
        $cliente = Cliente::firstOrCreate(['numero_contacto' => '5491199999999']);
        foreach ($sectores as $sector => $n) {
            Reserva::create([
                'id_cliente' => $cliente->id, 'rama_servicio' => 'RESTAURANTE', 'estado_reserva' => 'CONFIRMADA',
                'datos' => ['fecha' => '12/10/26', 'hora' => $hora, 'numero_personas' => "{$n} personas", 'sector_key' => $sector],
            ]);
        }
    }

    // ─── Seguridad ───────────────────────────────────────────────────────────

    public function test_firma_invalida_devuelve_432(): void
    {
        $this->enviar($this->cifrar(['action' => 'ping']), 'sha256=' . str_repeat('0', 64))->assertStatus(432);
    }

    public function test_payload_que_no_se_puede_descifrar_devuelve_421(): void
    {
        $otra = RSA::createKey(2048);
        config(['whatsapp_flows.private_key' => base64_encode($otra->toString('PKCS8'))]);

        $this->enviar($this->cifrar(['action' => 'ping']))->assertStatus(421);
    }

    public function test_ping_responde_active_sin_token(): void
    {
        $this->assertSame(['data' => ['status' => 'active']], $this->flow('ping', [], ''));
    }

    public function test_token_invalido_devuelve_427(): void
    {
        $this->enviar($this->cifrar(['action' => 'INIT', 'flow_token' => 'inventado']))->assertStatus(427);
    }

    public function test_notificacion_de_error_se_confirma(): void
    {
        $res = $this->flow('data_exchange', ['error' => 'INVALID_SCREEN', 'error_message' => 'x']);

        $this->assertSame(['data' => ['acknowledged' => true]], $res);
    }

    // ─── Pantallas ───────────────────────────────────────────────────────────

    public function test_init_muestra_fecha_y_personas_con_tope_de_7_dias(): void
    {
        $res = $this->flow('INIT');

        $this->assertSame('FECHA_PERSONAS', $res['screen']);
        $this->assertSame('2026-10-05', $res['data']['min_fecha']);
        $this->assertSame('2026-10-12', $res['data']['max_fecha']);
        $this->assertCount(15, $res['data']['personas_opciones']);
        $this->assertSame('15+', end($res['data']['personas_opciones'])['id']);
        $this->assertFalse($res['data']['hay_error']);
    }

    public function test_buscar_devuelve_turnos_y_sectores_con_el_nombre_precargado(): void
    {
        Cliente::create(['numero_contacto' => '5491100000001', 'nombre_cliente' => 'Martina']);

        $res = $this->flow('data_exchange', ['accion' => 'buscar', 'fecha' => self::LUNES, 'personas' => '4']);

        $this->assertSame('DATOS', $res['screen']);
        $this->assertSame(['11:45', '14:00', '12:00', '20:00', '22:00'], array_column($res['data']['turnos'], 'id'));
        $this->assertSame('12:00 a 16:00 hs', $res['data']['turnos'][2]['title']);
        $this->assertSame('11:45', $res['data']['turno_sel']);
        $this->assertContains('sin_preferencia', array_column($res['data']['sectores'], 'id'));
        $this->assertSame('Martina', $res['data']['nombre']);
        $this->assertSame('Lunes 12/10 · 4 personas', $res['data']['resumen']);
    }

    public function test_buscar_15_o_mas_va_a_la_pantalla_de_grupo(): void
    {
        $res = $this->flow('data_exchange', ['accion' => 'buscar', 'fecha' => self::LUNES, 'personas' => '15+']);

        $this->assertSame('GRUPO', $res['screen']);
    }

    public function test_buscar_fuera_de_rango_muestra_error_en_pantalla(): void
    {
        $res = $this->flow('data_exchange', ['accion' => 'buscar', 'fecha' => '2026-10-13', 'personas' => '2']);

        $this->assertSame('FECHA_PERSONAS', $res['screen']);
        $this->assertTrue($res['data']['hay_error']);
        $this->assertStringContainsString('7 días', $res['data']['error_msg']);
    }

    public function test_finde_mismo_dia_despues_de_las_11_avisa_orden_de_llegada(): void
    {
        Carbon::setTestNow('2026-10-10 11:15:00'); // sábado
        $this->token = WhatsAppFlowToken::emitir($this->session, WhatsAppFlowToken::FLOW_RESERVA, 24);

        $res = $this->flow('data_exchange', ['accion' => 'buscar', 'fecha' => '2026-10-10', 'personas' => '2']);

        $this->assertSame('FECHA_PERSONAS', $res['screen']);
        $this->assertStringContainsString('orden de llegada', $res['data']['error_msg']);
    }

    public function test_dia_sin_lugar_en_ningun_turno_muestra_error(): void
    {
        $llenos = ['salon' => 36, 'galeria' => 48, 'terraza' => 14, 'parrilla' => 14, 'patio' => 55];
        foreach (['11:45', '14:00', '12:00', '20:00', '22:00'] as $hora) {
            $this->ocupar($hora, $llenos);
        }

        $res = $this->flow('data_exchange', ['accion' => 'buscar', 'fecha' => self::LUNES, 'personas' => '2']);

        $this->assertSame('FECHA_PERSONAS', $res['screen']);
        $this->assertStringContainsString('No nos quedan lugares', $res['data']['error_msg']);
    }

    public function test_elegir_turno_actualiza_los_sectores_y_conserva_lo_escrito(): void
    {
        $this->ocupar('20:00', ['salon' => 36]);

        $res = $this->flow('data_exchange', [
            'accion' => 'turno', 'fecha' => self::LUNES, 'personas' => '4', 'turno' => '20:00',
            'nombre' => 'Juan', 'comentarios' => 'Ventana',
        ]);

        $this->assertSame('20:00', $res['data']['turno_sel']);
        $this->assertNotContains('salon', array_column($res['data']['sectores'], 'id'));
        $this->assertSame('Juan', $res['data']['nombre']);
        $this->assertSame('Ventana', $res['data']['comentarios']);
    }

    // ─── Confirmación ────────────────────────────────────────────────────────

    public function test_confirmar_crea_la_reserva_y_cierra_el_flow(): void
    {
        $res = $this->confirmar();

        $this->assertSame('SUCCESS', $res['screen']);
        $params = $res['data']['extension_message_response']['params'];
        $this->assertSame($this->token->token, $params['flow_token']);
        $this->assertSame('reserva', $params['resultado']);

        $reserva = Reserva::findOrFail($params['reserva_id']);
        $this->assertSame('CONFIRMADA', $reserva->estado_reserva);
        $this->assertSame('RESTAURANTE', $reserva->rama_servicio);
        $this->assertSame([
            'fecha' => '12/10/26', 'hora' => '20:00', 'numero_personas' => '4 personas', 'sector' => 'Galería',
            'sector_key' => 'galeria', 'nombre_responsable' => 'Martina', 'comentarios' => 'Es un cumple',
            'es_finde' => false, 'origen' => 'whatsapp_flow', 'fecha_es_futura' => false,
        ], $reserva->datos);

        $cliente = Cliente::where('numero_contacto', '5491100000001')->first();
        $this->assertSame('Martina', $cliente->nombre_cliente, 'Cliente nuevo: toma el nombre del Flow');
        $this->assertSame(1, (int) $cliente->contador_reservas_restaurante);
        $this->assertSame('COMPLETADO', $this->session->fresh()->estado_actual);
        $this->assertNotNull($this->token->fresh()->completed_at);
    }

    public function test_no_pisa_el_nombre_de_un_cliente_registrado(): void
    {
        Cliente::create(['numero_contacto' => '5491100000001', 'nombre_cliente' => 'Martina']);

        $this->confirmar(['nombre' => 'Reserva de Pedro']);

        $this->assertSame('Martina', Cliente::first()->nombre_cliente);
        $this->assertSame('Reserva de Pedro', Reserva::first()->datos['nombre_responsable']);
    }

    public function test_el_token_no_se_puede_reusar_despues_de_confirmar(): void
    {
        $this->confirmar();

        $this->enviar($this->cifrar(['action' => 'INIT', 'flow_token' => $this->token->token]))->assertStatus(427);
    }

    public function test_si_el_sector_se_lleno_vuelve_a_datos_con_error_y_sin_reservar(): void
    {
        $this->ocupar('20:00', ['galeria' => 45]); // quedan 3 lugares en Galería a las 20

        $res = $this->confirmar();

        $this->assertSame('DATOS', $res['screen']);
        $this->assertTrue($res['data']['hay_error']);
        $this->assertStringContainsString('sector se acaba de llenar', $res['data']['error_msg']);
        $this->assertNotContains('galeria', array_column($res['data']['sectores'], 'id'));
        $this->assertSame('Martina', $res['data']['nombre']);
        $this->assertSame(1, Reserva::count());
    }

    public function test_si_el_turno_se_lleno_vuelve_a_elegir_horario(): void
    {
        $this->ocupar('20:00', ['salon' => 36, 'galeria' => 48, 'terraza' => 14, 'parrilla' => 14, 'patio' => 55]);

        $res = $this->confirmar();

        $this->assertSame('DATOS', $res['screen']);
        $this->assertStringContainsString('horario se acaba de llenar', $res['data']['error_msg']);
        $this->assertNotContains('20:00', array_column($res['data']['turnos'], 'id'));
        $this->assertSame(5, Reserva::count());
    }

    public function test_sin_aceptar_tyc_no_reserva(): void
    {
        $res = $this->confirmar(['acepta_tyc' => false]);

        $this->assertSame('DATOS', $res['screen']);
        $this->assertStringContainsString('Términos', $res['data']['error_msg']);
        $this->assertSame(0, Reserva::count());
    }

    public function test_sin_preferencia_guarda_la_reserva_sin_sector(): void
    {
        $res = $this->confirmar(['sector' => 'sin_preferencia']);

        $this->assertSame('SUCCESS', $res['screen']);
        $this->assertNull(Reserva::first()->datos['sector_key']);
        $this->assertSame('Sin preferencia', Reserva::first()->datos['sector']);
    }

    public function test_grupo_completa_sin_crear_reserva_y_deja_los_datos_para_el_asesor(): void
    {
        $res = $this->flow('data_exchange', [
            'accion' => 'grupo', 'fecha' => self::LUNES, 'nombre' => 'Club de lectura',
            'cantidad' => '22', 'comentarios' => 'Mediodía', 'acepta_tyc' => true,
        ]);

        $this->assertSame('SUCCESS', $res['screen']);
        $this->assertSame('grupo', $res['data']['extension_message_response']['params']['resultado']);
        $this->assertSame(0, Reserva::count());
        $this->assertSame('22 personas', $this->session->fresh()->datos_parciales['numero_personas']);
    }

    public function test_grupo_con_menos_de_15_pide_volver_atras(): void
    {
        $res = $this->flow('data_exchange', [
            'accion' => 'grupo', 'fecha' => self::LUNES, 'nombre' => 'X', 'cantidad' => '8', 'acepta_tyc' => true,
        ]);

        $this->assertSame('GRUPO', $res['screen']);
        $this->assertStringContainsString('menos de 15', $res['data']['error_msg']);
    }
}
