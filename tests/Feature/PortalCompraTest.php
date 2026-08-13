<?php

namespace Tests\Feature;

use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\Nacionalidad;
use App\Models\Pago;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Models\VerificacionCorreo;
use App\Services\Cliente\VerificacionCorreoService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El recorrido del visitante, de punta a punta.
 */
class PortalCompraTest extends TestCase
{
    use RefreshDatabase;

    private Rubro $rubro;
    private string $fechaVisita;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(CatalogosSeeder::class);

        $this->rubro = Rubro::create([
            'tipo'               => 'Adulto nacional',
            'id_nacionalidad'    => Nacionalidad::where('nombre', 'NACIONAL')->value('id'),
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'ADULTO NACIONAL')->value('id'),
            'id_tipo_acceso'     => TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->value('id'),
            'precio_centavos'    => 4000,
            'vigente_desde'      => now()->subMonth(),
            'activo'             => true,
        ]);

        $this->fechaVisita = Carbon::parse('next tuesday')->toDateString();
        AforoDiario::create(['fecha' => $this->fechaVisita, 'cupo_maximo' => 100, 'reservados' => 0]);
    }

    private function clienteVerificado(): Cliente
    {
        return Cliente::create([
            'correo'               => 'ana@example.com',
            'password'             => Hash::make('secreto12345'),
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
        ]);
    }

    private function carrito(int $hombres = 1, int $mujeres = 1): array
    {
        return [
            'fecha_visita' => $this->fechaVisita,
            'renglones'    => [[
                'id_rubro'    => $this->rubro->id,
                'cant_hombre' => $hombres,
                'cant_mujer'  => $mujeres,
            ]],
        ];
    }

    // ═══ Portal público ═══

    public function test_el_inicio_muestra_las_tarifas_vigentes(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Adulto nacional')
            ->assertSee('$40.00')
            ->assertSee('Lunes cerrado');
    }

    // ═══ Registro y verificación ═══

    public function test_el_registro_emite_un_codigo_y_deja_el_correo_sin_verificar(): void
    {
        $this->post('/registro', [
            'correo'                => 'nueva@example.com',
            'password'              => 'secreto12345',
            'password_confirmation' => 'secreto12345',
            'nombre'                => 'Nueva',
            'apellidos'             => 'Visitante',
            'fecha_nacimiento'      => '1995-03-10',
            'genero'                => 'Mujer',
            'telefono'              => '9610001111',
        ])->assertRedirect();

        $cliente = Cliente::where('correo', 'nueva@example.com')->firstOrFail();

        $this->assertNull($cliente->correo_verificado_en);
        $this->assertFalse($cliente->correoVerificado());
        $this->assertSame(1, VerificacionCorreo::where('correo', 'nueva@example.com')->count());
    }

    public function test_el_codigo_se_guarda_hasheado_no_en_claro(): void
    {
        $codigo = app(VerificacionCorreoService::class)->emitir('x@example.com');

        $fila = VerificacionCorreo::where('correo', 'x@example.com')->firstOrFail();

        $this->assertNotSame($codigo, $fila->codigo_hash);
        $this->assertTrue(Hash::check($codigo, $fila->codigo_hash));
    }

    public function test_un_codigo_incorrecto_no_verifica_y_cuenta_el_intento(): void
    {
        app(VerificacionCorreoService::class)->emitir('ana@example.com');
        $this->clienteVerificado()->update(['correo_verificado_en' => null]);

        $this->post('/registro/verificar', ['correo' => 'ana@example.com', 'codigo' => '000000'])
            ->assertSessionHasErrors('codigo');

        $this->assertGuest('cliente');
    }

    public function test_el_codigo_correcto_verifica_e_inicia_sesion(): void
    {
        $cliente = $this->clienteVerificado();
        $cliente->update(['correo_verificado_en' => null]);

        $codigo = app(VerificacionCorreoService::class)->emitir('ana@example.com');

        $this->post('/registro/verificar', ['correo' => 'ana@example.com', 'codigo' => $codigo])
            ->assertRedirect(route('compras.crear'));

        $this->assertAuthenticated('cliente');
        $this->assertNotNull($cliente->fresh()->correo_verificado_en);
    }

    public function test_un_codigo_expirado_se_rechaza(): void
    {
        $codigo = app(VerificacionCorreoService::class)->emitir('ana@example.com');

        VerificacionCorreo::where('correo', 'ana@example.com')
            ->update(['expira_en' => now()->subMinute()]);

        $this->post('/registro/verificar', ['correo' => 'ana@example.com', 'codigo' => $codigo])
            ->assertSessionHasErrors('codigo');
    }

    // ═══ Compra ═══

    public function test_comprar_exige_sesion_de_cliente(): void
    {
        $this->get('/comprar')->assertRedirect();
        $this->post('/comprar', $this->carrito())->assertRedirect();

        $this->assertSame(0, Compra::count());
    }

    public function test_la_cotizacion_del_portal_la_calcula_el_servidor(): void
    {
        $this->actingAs($this->clienteVerificado(), 'cliente')
            ->postJson('/comprar/cotizar', $this->carrito(1, 1))
            ->assertOk()
            ->assertJson(['total_centavos' => 8000, 'pases' => 2]);
    }

    public function test_una_compra_completa_queda_pendiente_y_reserva_aforo(): void
    {
        $this->actingAs($this->clienteVerificado(), 'cliente')
            ->post('/comprar', $this->carrito(1, 1))
            ->assertRedirect();

        $compra = Compra::firstOrFail();

        $this->assertSame(Compra::PENDIENTE_PAGO, $compra->estado);
        $this->assertSame(8000, $compra->total_centavos);
        $this->assertSame(2, $compra->pases_total);
        $this->assertNull($compra->qr_token);
        $this->assertSame(2, AforoDiario::find($this->fechaVisita)->reservados);

        // Se registró el pago iniciado con su referencia.
        $this->assertSame(Pago::INICIADO, Pago::where('id_compra', $compra->id)->first()->estado);
    }

    public function test_el_flujo_completo_hasta_el_qr(): void
    {
        $cliente = $this->clienteVerificado();

        $this->actingAs($cliente, 'cliente')->post('/comprar', $this->carrito(1, 1));

        $compra = Compra::firstOrFail();
        $pago   = Pago::where('id_compra', $compra->id)->firstOrFail();

        // La pantalla de retorno NO emite el QR.
        $this->actingAs($cliente, 'cliente')
            ->get(route('compras.retorno', $compra->folio))
            ->assertOk()
            ->assertSee('Estamos confirmando tu pago');

        $this->assertNull($compra->fresh()->qr_token);

        // El pago se confirma por la pasarela simulada, que firma el webhook.
        $this->actingAs($cliente, 'cliente')
            ->post(route('pago.simulado.confirmar', $pago->referencia_externa), ['resultado' => 'aprobar'])
            ->assertRedirect();

        $compra->refresh();
        $this->assertSame(Compra::PAGADA, $compra->estado);
        $this->assertNotNull($compra->qr_token);

        // Y ahora sí hay imagen de QR.
        $respuesta = $this->actingAs($cliente, 'cliente')->get(route('compras.qr', $compra->folio));
        $respuesta->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $respuesta->getContent());
    }

    public function test_un_pago_rechazado_no_emite_qr(): void
    {
        $cliente = $this->clienteVerificado();
        $this->actingAs($cliente, 'cliente')->post('/comprar', $this->carrito(1, 1));

        $compra = Compra::firstOrFail();
        $pago   = Pago::where('id_compra', $compra->id)->firstOrFail();

        $this->actingAs($cliente, 'cliente')
            ->post(route('pago.simulado.confirmar', $pago->referencia_externa), ['resultado' => 'rechazar']);

        $compra->refresh();
        $this->assertSame(Compra::PENDIENTE_PAGO, $compra->estado);
        $this->assertNull($compra->qr_token);
    }

    // ═══ Aislamiento entre clientes ═══

    public function test_un_cliente_no_puede_ver_la_compra_de_otro(): void
    {
        $ana = $this->clienteVerificado();
        $this->actingAs($ana, 'cliente')->post('/comprar', $this->carrito(1, 1));
        $compra = Compra::firstOrFail();

        $luis = Cliente::create([
            'correo'               => 'luis@example.com',
            'password'             => Hash::make('secreto12345'),
            'nombre'               => 'Luis',
            'apellidos'            => 'Gómez',
            'fecha_nacimiento'     => '1988-07-07',
            'genero'               => 'Hombre',
            'telefono'             => '9612222222',
            'correo_verificado_en' => now(),
        ]);

        // 404 y no 403: no confirmamos que ese folio exista.
        $this->actingAs($luis, 'cliente')
            ->get(route('compras.ver', $compra->folio))
            ->assertNotFound();

        $this->actingAs($luis, 'cliente')
            ->get(route('compras.qr', $compra->folio))
            ->assertNotFound();
    }

    // ═══ §10.6 — Aislamiento de guards, ahora sobre el portal ═══

    public function test_una_cuenta_interna_no_puede_comprar(): void
    {
        $admin = Cuenta::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin, 'web')->get('/comprar')->assertForbidden();
        $this->actingAs($admin, 'web')->post('/comprar', $this->carrito())->assertForbidden();

        $this->assertSame(0, Compra::count());
    }

    public function test_una_cuenta_interna_no_puede_registrarse_como_cliente(): void
    {
        $admin = Cuenta::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin, 'web')->get('/registro')->assertForbidden();
        $this->actingAs($admin, 'web')->get('/ingresar')->assertForbidden();
    }
}
