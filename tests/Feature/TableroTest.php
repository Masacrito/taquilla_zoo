<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\Nacionalidad;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Models\Usuario;
use App\Services\Venta\CotizarCompraService;
use App\Services\Venta\RegistrarCompraService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TableroTest extends TestCase
{
    use RefreshDatabase;

    private Rubro $rubro;
    private string $hoy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);
        $this->seed(CatalogosSeeder::class);

        $this->hoy = now()->toDateString();

        $this->rubro = Rubro::create([
            'tipo'               => 'Adulto',
            'id_nacionalidad'    => Nacionalidad::where('nombre', 'NACIONAL')->value('id'),
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'ADULTO NACIONAL')->value('id'),
            'id_tipo_acceso'     => TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->value('id'),
            'precio_centavos'    => 3500,
            'vigente_desde'      => now()->subMonth(),
            'activo'             => true,
        ]);

        AforoDiario::firstOrCreate(['fecha' => $this->hoy], ['cerrado' => false]);

        // El Super Admin con correo real, para que ese aviso no ensucie las
        // pruebas que no van de eso.
        Usuario::where('id_usuario', Cuenta::SUPER_ADMIN_ID)
            ->update(['email' => 'super@semahn.chiapas.gob.mx']);
    }

    private function admin(): Cuenta
    {
        return Cuenta::superAdmin();
    }

    private function comprarYPagar(int $pases): Compra
    {
        $cliente = Cliente::create([
            'correo'               => 'cli' . uniqid() . '@example.com',
            'password'             => Hash::make('secreto12345'),
            'nombre'               => 'Cliente',
            'apellidos'            => 'Prueba',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
        ]);

        $cotizacion = app(CotizarCompraService::class)->cotizar(
            [['id_rubro' => $this->rubro->id, 'cant_hombre' => $pases, 'cant_mujer' => 0]],
            $this->hoy,
        );

        $compra = app(RegistrarCompraService::class)->registrar($cliente, $cotizacion, $this->hoy);
        $compra->update(['estado' => Compra::PAGADA]);

        return $compra->refresh();
    }

    // ═══ Los números de hoy ═══

    public function test_el_tablero_suma_lo_vendido_y_los_pases_de_hoy(): void
    {
        $this->comprarYPagar(3);
        $this->comprarYPagar(2);

        $this->actingAs($this->admin(), 'web')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('$175.00')          // 5 pases × $35
            ->assertSee('2 compras');
    }

    /**
     * «Vendido hoy» y «gente que viene hoy» son cosas distintas: se puede
     * comprar el martes para visitar el sábado. El tablero tiene que
     * distinguirlas o la puerta no sabe a cuánta gente esperar.
     */
    public function test_lo_comprado_para_otro_dia_no_cuenta_como_visita_de_hoy(): void
    {
        $otroDia = now()->addDays(3)->toDateString();
        AforoDiario::firstOrCreate(['fecha' => $otroDia], ['cerrado' => false]);

        $cliente = Cliente::create([
            'correo' => 'futuro@example.com', 'password' => Hash::make('secreto12345'),
            'nombre' => 'Futuro', 'apellidos' => 'Visitante', 'fecha_nacimiento' => '1990-01-01',
            'genero' => 'Mujer', 'telefono' => '9610000000', 'correo_verificado_en' => now(),
        ]);

        $cotizacion = app(CotizarCompraService::class)->cotizar(
            [['id_rubro' => $this->rubro->id, 'cant_hombre' => 4, 'cant_mujer' => 0]],
            $otroDia,
        );
        app(RegistrarCompraService::class)->registrar($cliente, $cotizacion, $otroDia)
            ->update(['estado' => Compra::PAGADA]);

        $tablero = app(\App\Services\Reporte\TableroService::class)->generar();

        $this->assertSame(4, $tablero['hoy']['pases_vendidos'], 'Se vendió hoy.');
        $this->assertSame(0, $tablero['hoy']['pases_esperados'], 'Pero la visita es en tres días.');
    }

    public function test_el_tablero_cuenta_a_quien_ya_entro(): void
    {
        $compra = $this->comprarYPagar(4);

        Compra::consumirPases($compra->id, 3);
        Acceso::create([
            'id_compra' => $compra->id, 'id_torniquete' => 'T1', 'metodo' => 'qr',
            'pases_consumidos' => 3, 'escaneado_en' => now(), 'resultado' => Acceso::PERMITIDO,
        ]);

        $tablero = app(\App\Services\Reporte\TableroService::class)->generar();

        $this->assertSame(4, $tablero['hoy']['pases_esperados']);
        $this->assertSame(3, $tablero['hoy']['ya_entraron']);
    }

    public function test_un_acceso_rechazado_no_cuenta_como_entrada(): void
    {
        $compra = $this->comprarYPagar(2);

        Acceso::create([
            'id_compra' => $compra->id, 'id_torniquete' => 'T1', 'metodo' => 'qr',
            'pases_consumidos' => 0, 'escaneado_en' => now(),
            'resultado' => Acceso::RECHAZADO, 'motivo_rechazo' => 'Fecha equivocada',
        ]);

        $tablero = app(\App\Services\Reporte\TableroService::class)->generar();

        $this->assertSame(0, $tablero['hoy']['ya_entraron']);
    }

    // ═══ Los avisos ═══

    /**
     * La idea de diseño del tablero: los avisos son condicionales. Uno que
     * siempre enseña las mismas tarjetas en verde se vuelve invisible.
     */
    public function test_sin_problemas_no_hay_ningun_aviso(): void
    {
        // Calendario con holgura y tarifa vigente: nada que reportar.
        for ($i = 0; $i < 30; $i++) {
            AforoDiario::firstOrCreate(
                ['fecha' => now()->addDays($i)->toDateString()],
                ['cerrado' => false],
            );
        }

        $tablero = app(\App\Services\Reporte\TableroService::class)->generar();

        $this->assertSame([], $tablero['avisos']);
    }

    /**
     * El aviso más valioso del tablero.
     *
     * Cuando se acaban los días generados, /comprar deja de vender y hoy no
     * hay ningún síntoma en el panel: nadie se entera hasta que un visitante
     * se queja.
     */
    public function test_avisa_cuando_el_calendario_se_esta_agotando(): void
    {
        $tablero = app(\App\Services\Reporte\TableroService::class)->generar();

        // En setUp solo se abrió el día de hoy.
        $textos = collect($tablero['avisos'])->pluck('texto')->implode(' ');

        $this->assertStringContainsString('días abiertos en el calendario', $textos);
    }

    public function test_avisa_cuando_no_hay_ninguna_fecha_a_la_venta(): void
    {
        AforoDiario::query()->delete();

        $textos = collect(app(\App\Services\Reporte\TableroService::class)->generar()['avisos'])
            ->pluck('texto')->implode(' ');

        $this->assertStringContainsString('nadie puede comprar boletos', $textos);
    }

    public function test_avisa_cuando_no_hay_tarifas_vigentes(): void
    {
        $this->rubro->update(['activo' => false]);

        $textos = collect(app(\App\Services\Reporte\TableroService::class)->generar()['avisos'])
            ->pluck('texto')->implode(' ');

        $this->assertStringContainsString('no puede vender', $textos);
    }

    public function test_avisa_si_el_super_admin_no_tiene_correo_real(): void
    {
        Usuario::where('id_usuario', Cuenta::SUPER_ADMIN_ID)
            ->update(['email' => 'admin@example.com']);

        $textos = collect(app(\App\Services\Reporte\TableroService::class)->generar()['avisos'])
            ->pluck('texto')->implode(' ');

        $this->assertStringContainsString('no llegan a nadie', $textos);
    }

    // El acceso de Taquilla al tablero ya lo cubre
    // VistasInternasTest::test_taquilla_no_entra_al_dashboard_de_admin, que
    // además usa la convención real del proyecto: `EnsureRole` devuelve al
    // dashboard propio en vez de responder 403.
}
