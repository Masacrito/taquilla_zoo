<?php

namespace Tests\Feature;

use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\Nacionalidad;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Models\Usuario;
use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\NotificacionPago;
use App\Services\Venta\CotizarCompraService;
use App\Services\Venta\RegistrarCompraService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PantallaAccesosTest extends TestCase
{
    use RefreshDatabase;

    private Rubro $rubro;
    private string $hoy;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);
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

        $this->hoy = Carbon::today()->toDateString();
        AforoDiario::create(['fecha' => $this->hoy, 'cupo_maximo' => 100]);
    }

    private function taquillero(): Cuenta
    {
        $usuario = Usuario::create(['nombre' => 'Cajero Uno', 'puesto' => 'Taquilla']);

        return Cuenta::create([
            'username'   => 'cajero1',
            'password'   => Hash::make('secreto123'),
            'estado'     => 'activo',
            'id_usuario' => $usuario->id_usuario,
            'id_rol'     => 2,
        ]);
    }

    private function compraPagada(int $pases = 2): Compra
    {
        $cliente = Cliente::create([
            'correo' => 'ana' . uniqid() . '@example.com', 'password' => Hash::make('secreto12345'),
            'nombre' => 'Ana', 'apellidos' => 'Pérez', 'fecha_nacimiento' => '1990-01-01',
            'genero' => 'Mujer', 'telefono' => '9610000000', 'correo_verificado_en' => now(),
        ]);

        $cotizacion = app(CotizarCompraService::class)->cotizar(
            [['id_rubro' => $this->rubro->id, 'cant_hombre' => $pases, 'cant_mujer' => 0]], $this->hoy,
        );
        $compra = app(RegistrarCompraService::class)->registrar($cliente, $cotizacion, $this->hoy);

        app(ConfirmarPagoService::class)->confirmar(new NotificacionPago(
            firmaValida: true, referenciaExterna: 'SIM-' . uniqid(), estado: 'aprobado',
            montoCentavos: $compra->total_centavos, autorizacion: 'A', payload: ['folio' => $compra->folio],
        ), 'simulada');

        return $compra->refresh();
    }

    // ═══ Permisos ═══

    public function test_taquilla_si_puede_escanear_y_ver_entradas(): void
    {
        $cajero = $this->taquillero();

        $this->actingAs($cajero, 'web')->get('/accesos/escanear')->assertOk();
        $this->actingAs($cajero, 'web')->get('/accesos/bitacora')->assertOk();
    }

    public function test_un_cliente_no_alcanza_el_modulo_de_accesos(): void
    {
        $cliente = Cliente::create([
            'correo' => 'intruso@example.com', 'password' => Hash::make('secreto12345'),
            'nombre' => 'Intruso', 'apellidos' => 'X', 'fecha_nacimiento' => '1990-01-01',
            'genero' => 'Hombre', 'telefono' => '9610000009', 'correo_verificado_en' => now(),
        ]);

        $this->actingAs($cliente, 'cliente')->get('/accesos/escanear')->assertForbidden();
        $this->actingAs($cliente, 'cliente')->post('/accesos/validar', ['codigo' => 'x', 'pases' => 1])->assertForbidden();
    }

    public function test_sin_sesion_no_se_puede_validar(): void
    {
        $compra = $this->compraPagada();

        $this->post('/accesos/validar', ['codigo' => $compra->qr_token, 'pases' => 1])->assertRedirect();

        $this->assertSame(0, $compra->refresh()->pases_usados);
    }

    // ═══ Validación por HTTP ═══

    public function test_validar_devuelve_json_con_el_veredicto(): void
    {
        $compra = $this->compraPagada(2);

        $this->actingAs($this->taquillero(), 'web')
            ->postJson('/accesos/validar', ['codigo' => $compra->qr_token, 'pases' => 1])
            ->assertOk()
            ->assertJson([
                'permitido' => true,
                'compra'    => ['folio' => $compra->folio, 'pases_restantes' => 1],
            ]);

        $this->assertSame(1, $compra->refresh()->pases_usados);
    }

    public function test_validar_un_token_falsificado_devuelve_rechazo(): void
    {
        // Con forma de token (4 partes) pero firma inventada.
        $this->actingAs($this->taquillero(), 'web')
            ->postJson('/accesos/validar', ['codigo' => '1.ZM-2026-000001.2026-08-13.firmafalsa', 'pases' => 1])
            ->assertOk()
            ->assertJson(['permitido' => false, 'motivo' => 'token_invalido']);
    }

    public function test_un_folio_inexistente_devuelve_rechazo(): void
    {
        $this->actingAs($this->taquillero(), 'web')
            ->postJson('/accesos/validar', ['codigo' => 'ZM-9999-000999', 'pases' => 1])
            ->assertOk()
            ->assertJson(['permitido' => false, 'motivo' => 'no_encontrada']);
    }

    // ═══ Consultar no consume ═══

    public function test_consultar_no_descuenta_pases(): void
    {
        $compra = $this->compraPagada(3);
        $cajero = $this->taquillero();

        // Tres consultas seguidas, como cuando el visitante deja el código
        // frente a la cámara.
        foreach (range(1, 3) as $i) {
            $this->actingAs($cajero, 'web')
                ->postJson('/accesos/consultar', ['codigo' => $compra->qr_token])
                ->assertOk()
                ->assertJson(['permitido' => true, 'consumido' => 0]);
        }

        $this->assertSame(0, $compra->refresh()->pases_usados);
        // Y no ensucian la bitácora: las consultas válidas no se asientan.
        $this->assertSame(0, \App\Models\Acceso::count());
    }

    public function test_consultar_por_folio_funciona_como_contingencia(): void
    {
        $compra = $this->compraPagada(2);

        $this->actingAs($this->taquillero(), 'web')
            ->postJson('/accesos/consultar', ['codigo' => $compra->folio])
            ->assertOk()
            ->assertJson([
                'permitido' => true,
                'compra'    => ['folio' => $compra->folio, 'pases_restantes' => 2],
            ]);
    }

    public function test_la_entrada_por_folio_queda_marcada_en_la_bitacora(): void
    {
        $compra = $this->compraPagada(2);

        $this->actingAs($this->taquillero(), 'web')
            ->postJson('/accesos/validar', ['codigo' => $compra->folio, 'pases' => 1])
            ->assertOk()
            ->assertJson(['permitido' => true]);

        $acceso = \App\Models\Acceso::latest('id')->firstOrFail();
        $this->assertSame(\App\Models\Acceso::METODO_FOLIO, $acceso->metodo);
    }

    public function test_la_entrada_por_qr_queda_marcada_como_qr(): void
    {
        $compra = $this->compraPagada(2);

        $this->actingAs($this->taquillero(), 'web')
            ->postJson('/accesos/validar', ['codigo' => $compra->qr_token, 'pases' => 1]);

        $this->assertSame(\App\Models\Acceso::METODO_QR, \App\Models\Acceso::latest('id')->first()->metodo);
    }

    public function test_la_bitacora_muestra_los_escaneos(): void
    {
        $compra = $this->compraPagada(1);
        $cajero = $this->taquillero();

        $this->actingAs($cajero, 'web')
            ->postJson('/accesos/validar', ['codigo' => $compra->qr_token, 'pases' => 1]);

        $this->actingAs($cajero, 'web')->get('/accesos/bitacora')
            ->assertOk()
            ->assertSee($compra->folio)
            ->assertSee('permitido');
    }
}
