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
use App\Services\Reporte\CorteIngresosService;
use App\Services\Reporte\EstadisticaService;
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

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private Rubro $adulto;
    private Rubro $nino;
    private string $hoy;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);
        $this->seed(CatalogosSeeder::class);

        $pago = TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->value('id');
        $nac  = Nacionalidad::where('nombre', 'NACIONAL')->value('id');

        $this->adulto = Rubro::create([
            'tipo' => 'Adulto nacional', 'id_nacionalidad' => $nac,
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'ADULTO NACIONAL')->value('id'),
            'id_tipo_acceso' => $pago, 'precio_centavos' => 4000,
            'vigente_desde' => now()->subMonth(), 'activo' => true,
        ]);

        $this->nino = Rubro::create([
            'tipo' => 'Niño nacional', 'id_nacionalidad' => $nac,
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'NIÑO NACIONAL')->value('id'),
            'id_tipo_acceso' => $pago, 'precio_centavos' => 2000,
            'vigente_desde' => now()->subMonth(), 'activo' => true,
        ]);

        $this->hoy = Carbon::today()->toDateString();
        AforoDiario::create(['fecha' => $this->hoy, 'cupo_maximo' => 500]);
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'correo' => 'c' . uniqid() . '@example.com', 'password' => Hash::make('secreto12345'),
            'nombre' => 'Ana', 'apellidos' => 'Pérez', 'fecha_nacimiento' => '1990-01-01',
            'genero' => 'Mujer', 'telefono' => '9610000000', 'correo_verificado_en' => now(),
        ]);
    }

    private function comprar(array $renglones, bool $pagar = true): Compra
    {
        $cotizacion = app(CotizarCompraService::class)->cotizar($renglones, $this->hoy);
        $compra = app(RegistrarCompraService::class)->registrar($this->cliente(), $cotizacion, $this->hoy);

        if ($pagar) {
            app(ConfirmarPagoService::class)->confirmar(new NotificacionPago(
                firmaValida: true, referenciaExterna: 'SIM-' . uniqid(), estado: 'aprobado',
                montoCentavos: $compra->total_centavos, autorizacion: 'A',
                payload: ['folio' => $compra->folio],
            ), 'simulada');
        }

        return $compra->refresh();
    }

    private function admin(): Cuenta
    {
        return Cuenta::where('username', 'admin')->firstOrFail();
    }

    private function taquillero(): Cuenta
    {
        $usuario = Usuario::create(['nombre' => 'Cajero', 'puesto' => 'Taquilla']);

        return Cuenta::create([
            'username' => 'cajero1', 'password' => Hash::make('secreto123'),
            'estado' => 'activo', 'id_usuario' => $usuario->id_usuario, 'id_rol' => 2,
        ]);
    }

    // ═══ Corte de ingresos ═══

    public function test_el_corte_suma_solo_lo_cobrado(): void
    {
        // 2 adultos ($80) + 1 niño ($20) = $100, pagada
        $this->comprar([
            ['id_rubro' => $this->adulto->id, 'cant_hombre' => 1, 'cant_mujer' => 1],
            ['id_rubro' => $this->nino->id,   'cant_hombre' => 1, 'cant_mujer' => 0],
        ]);

        // Otra compra SIN pagar: no debe contar.
        $this->comprar([['id_rubro' => $this->adulto->id, 'cant_hombre' => 5, 'cant_mujer' => 0]], pagar: false);

        $corte = app(CorteIngresosService::class)->generar($this->hoy, $this->hoy);

        $this->assertSame(10000, $corte['resumen']['total_centavos']);
        $this->assertSame(3, $corte['resumen']['pases']);
        $this->assertSame(1, $corte['resumen']['compras']);

        // La no cobrada aparece explicada aparte.
        $pendientes = $corte['no_cobradas']->firstWhere('estado', Compra::PENDIENTE_PAGO);
        $this->assertNotNull($pendientes);
        $this->assertSame(20000, (int) $pendientes->total_centavos);
    }

    public function test_el_corte_desglosa_por_concepto(): void
    {
        $this->comprar([
            ['id_rubro' => $this->adulto->id, 'cant_hombre' => 2, 'cant_mujer' => 1],
            ['id_rubro' => $this->nino->id,   'cant_hombre' => 0, 'cant_mujer' => 2],
        ]);

        $corte = app(CorteIngresosService::class)->generar($this->hoy, $this->hoy);

        $adultos = $corte['por_rubro']->firstWhere('concepto', 'Adulto nacional');
        $ninos   = $corte['por_rubro']->firstWhere('concepto', 'Niño nacional');

        $this->assertSame(12000, (int) $adultos->total_centavos);   // 3 × $40
        $this->assertSame(4000,  (int) $ninos->total_centavos);     // 2 × $20
        $this->assertSame(2, (int) $adultos->hombres);
        $this->assertSame(2, (int) $ninos->mujeres);
    }

    /**
     * La razón de ser del snapshot (§4.3).
     */
    public function test_subir_una_tarifa_no_altera_un_corte_anterior(): void
    {
        $this->comprar([['id_rubro' => $this->adulto->id, 'cant_hombre' => 2, 'cant_mujer' => 0]]);

        $antes = app(CorteIngresosService::class)->generar($this->hoy, $this->hoy);
        $this->assertSame(8000, $antes['resumen']['total_centavos']);

        $this->adulto->update(['precio_centavos' => 9900]);

        $despues = app(CorteIngresosService::class)->generar($this->hoy, $this->hoy);
        $this->assertSame(8000, $despues['resumen']['total_centavos'], 'El corte histórico se movió.');
        $this->assertSame(4000, (int) $despues['por_rubro']->first()->precio_centavos);
    }

    public function test_una_compra_cancelada_no_cuenta_como_ingreso(): void
    {
        $compra = $this->comprar([['id_rubro' => $this->adulto->id, 'cant_hombre' => 2, 'cant_mujer' => 0]]);
        $compra->update(['estado' => Compra::CANCELADA]);

        $corte = app(CorteIngresosService::class)->generar($this->hoy, $this->hoy);

        $this->assertSame(0, $corte['resumen']['total_centavos']);
    }

    // ═══ Estadísticas ═══

    public function test_las_estadisticas_cuentan_solo_quien_entro(): void
    {
        // Pagada pero nadie se presentó: no es una visita.
        $this->comprar([['id_rubro' => $this->adulto->id, 'cant_hombre' => 3, 'cant_mujer' => 0]]);

        $datos = app(EstadisticaService::class)->generar($this->hoy, $this->hoy);
        $this->assertSame(0, $datos['resumen']['pases']);

        // Ahora sí entra alguien.
        $otra = $this->comprar([['id_rubro' => $this->adulto->id, 'cant_hombre' => 1, 'cant_mujer' => 1]]);
        Compra::consumirPases($otra->id, 2);
        $otra->update(['estado' => Compra::UTILIZADA]);

        $datos = app(EstadisticaService::class)->generar($this->hoy, $this->hoy);
        $this->assertSame(2, $datos['resumen']['pases']);
        $this->assertSame(1, $datos['resumen']['hombres']);
        $this->assertSame(1, $datos['resumen']['mujeres']);
    }

    public function test_las_estadisticas_agrupan_por_tipo_de_visitante(): void
    {
        $compra = $this->comprar([
            ['id_rubro' => $this->adulto->id, 'cant_hombre' => 2, 'cant_mujer' => 0],
            ['id_rubro' => $this->nino->id,   'cant_hombre' => 1, 'cant_mujer' => 0],
        ]);
        Compra::consumirPases($compra->id, 3);
        $compra->update(['estado' => Compra::UTILIZADA]);

        $datos = app(EstadisticaService::class)->generar($this->hoy, $this->hoy);

        $adultos = $datos['por_subnacionalidad']->firstWhere('etiqueta', 'ADULTO NACIONAL');
        $this->assertSame(2, (int) $adultos->pases);
        $this->assertSame(3, (int) $datos['por_nacionalidad']->firstWhere('etiqueta', 'NACIONAL')->pases);
    }

    // ═══ Permisos ═══

    public function test_taquilla_ve_cortes_pero_no_estadisticas(): void
    {
        $cajero = $this->taquillero();

        $this->actingAs($cajero, 'web')->get('/admin/cortes')->assertOk();
        $this->actingAs($cajero, 'web')->get('/admin/estadisticas')
            ->assertRedirect(route('taquilla.dashboard'));
    }

    public function test_el_admin_ve_ambos_reportes(): void
    {
        $this->comprar([['id_rubro' => $this->adulto->id, 'cant_hombre' => 1, 'cant_mujer' => 0]]);

        $this->actingAs($this->admin(), 'web')->get('/admin/cortes')
            ->assertOk()->assertSee('Adulto nacional');
        $this->actingAs($this->admin(), 'web')->get('/admin/estadisticas')->assertOk();
    }

    public function test_un_cliente_no_alcanza_los_reportes(): void
    {
        $this->actingAs($this->cliente(), 'cliente')->get('/admin/cortes')->assertForbidden();
        $this->actingAs($this->cliente(), 'cliente')->get('/admin/estadisticas')->assertForbidden();
    }
}
