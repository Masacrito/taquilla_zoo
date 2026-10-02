<?php

namespace Tests\Feature;

use App\Jobs\GenerarCalendario;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Services\Operacion\GenerarCalendarioService;
use App\Services\Reporte\TableroService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * El calendario se extiende solo, y avisa para que alguien lo revise.
 *
 * Todo corre con la fecha congelada a mitad de mes: el horizonte depende de
 * «hoy» y un test que pasa o falla según el día del mes no sirve.
 */
class CalendarioAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);

        Carbon::setTestNow('2026-10-15 09:00:00');
        config(['taquilla.calendario.meses_a_la_venta' => 3]);
    }

    private function correrTarea(): void
    {
        app()->call([new GenerarCalendario(), 'handle']);
    }

    private function avisos(): string
    {
        return collect(app(TableroService::class)->generar()['avisos'])->pluck('texto')->implode(' ');
    }

    public function test_la_tarea_abre_desde_hoy_hasta_el_fin_del_ultimo_mes_a_la_venta(): void
    {
        $this->correrTarea();

        // 15 oct → 31 dic: 17 + 30 + 31 días.
        $this->assertSame(78, AforoDiario::count());
        $this->assertNull(AforoDiario::find('2026-10-14'), 'No genera hacia atrás.');
        $this->assertNotNull(AforoDiario::find('2026-12-31'));
        $this->assertNull(AforoDiario::find('2027-01-01'));

        $martes = AforoDiario::find('2026-10-20');
        $this->assertFalse($martes->cerrado);
        $this->assertTrue($martes->pendienteDeRevision());

        // Los lunes siguen naciendo cerrados.
        $this->assertTrue(AforoDiario::find('2026-10-19')->cerrado);

        // Y queda rastro de que lo hizo el sistema (sin cuenta responsable).
        $movimiento = Movimiento::where('tabla', 'aforo_diario')->firstOrFail();
        $this->assertNull($movimiento->id_cuenta);
        $this->assertSame(78, $movimiento->detalles['creados']);
    }

    public function test_correrla_de_nuevo_no_duplica_ni_pisa_cierres(): void
    {
        AforoDiario::create(['fecha' => '2026-10-20', 'cerrado' => true, 'motivo_cierre' => 'Fumigación']);

        $this->correrTarea();
        $this->correrTarea();

        $this->assertSame(78, AforoDiario::count());

        $cerrado = AforoDiario::find('2026-10-20');
        $this->assertTrue($cerrado->cerrado);
        $this->assertSame('Fumigación', $cerrado->motivo_cierre);
        $this->assertFalse($cerrado->esAutomatico(), 'Lo abrió una persona: no es del sistema.');

        $this->assertSame(1, Movimiento::where('tabla', 'aforo_diario')->count(), 'La segunda corrida no creó nada.');
    }

    public function test_al_empezar_el_mes_solo_agrega_el_mes_nuevo(): void
    {
        $this->correrTarea();

        Carbon::setTestNow('2026-11-01 00:10:00');
        $nuevos = app(GenerarCalendarioService::class)->asegurarHorizonte();

        $this->assertSame(31, $nuevos->creados, 'Enero completo, y nada más.');
        $this->assertNotNull(AforoDiario::find('2027-01-31'));
    }

    public function test_el_tablero_avisa_hasta_que_se_marcan_como_revisados(): void
    {
        $admin = Cuenta::superAdmin();

        $this->correrTarea();

        $this->assertStringContainsString('Se generaron 78 días automáticamente', $this->avisos());

        $this->actingAs($admin, 'web')
            ->get('/admin/aforo?desde=2026-10-15&hasta=2026-10-31')
            ->assertOk()
            ->assertSee('sin revisar')
            ->assertSee('Marcar como revisados');

        // Revisar un rango solo apaga ese rango.
        $this->actingAs($admin, 'web')
            ->put('/admin/aforo/revisar', ['desde' => '2026-10-15', 'hasta' => '2026-10-31'])
            ->assertSessionHas('success');

        $this->assertStringContainsString('Se generaron 61 días automáticamente', $this->avisos());

        $this->actingAs($admin, 'web')
            ->put('/admin/aforo/revisar', ['desde' => '2026-11-01', 'hasta' => '2026-12-31']);

        $this->assertStringNotContainsString('automáticamente', $this->avisos());

        // Siguen marcados como automáticos, ya sin el pendiente.
        $this->actingAs($admin, 'web')
            ->get('/admin/aforo?desde=2026-10-15&hasta=2026-10-31')
            ->assertSee('automático')
            ->assertDontSee('sin revisar')
            ->assertDontSee('Marcar como revisados');
    }

    public function test_cerrar_un_dia_automatico_lo_da_por_revisado(): void
    {
        $this->correrTarea();

        $this->actingAs(Cuenta::superAdmin(), 'web')
            ->put('/admin/aforo/2026-10-20', ['cerrado' => 1, 'motivo_cierre' => 'Día festivo'])
            ->assertSessionHas('success');

        $dia = AforoDiario::find('2026-10-20');
        $this->assertTrue($dia->cerrado);
        $this->assertFalse($dia->pendienteDeRevision());
    }

    /** Antes de cerrar un día hay que saber a cuánta gente se le vendió. */
    public function test_la_pantalla_dice_cuantos_boletos_vigentes_tiene_cada_dia(): void
    {
        $this->correrTarea();

        $cliente = Cliente::create([
            'correo' => 'ana@example.com', 'password' => 'secreto12345', 'nombre' => 'Ana',
            'apellidos' => 'Prueba', 'fecha_nacimiento' => '1990-01-01', 'genero' => 'Mujer',
            'telefono' => '9610000000', 'correo_verificado_en' => now(),
        ]);

        $compra = fn (string $folio, string $estado, int $pases, int $usados = 0) => Compra::create([
            'folio' => $folio, 'id_cliente' => $cliente->id, 'fecha_compra' => now(),
            'fecha_visita' => '2026-10-20', 'total_centavos' => 3500 * $pases,
            'pases_total' => $pases, 'pases_usados' => $usados, 'estado' => $estado,
        ]);

        $compra('ZM-2026-000001', Compra::PAGADA, 4);
        $compra('ZM-2026-000002', Compra::ACCESO_PARCIAL, 3, 1);
        $compra('ZM-2026-000003', Compra::CANCELADA, 9);        // ya no cuenta
        $compra('ZM-2026-000004', Compra::PENDIENTE_PAGO, 9);   // todavía no cuenta

        $this->actingAs(Cuenta::superAdmin(), 'web')
            ->get('/admin/aforo?desde=2026-10-20&hasta=2026-10-20')
            ->assertOk()
            ->assertSeeInOrder(['2 compras', '6 pases']);
    }

    public function test_lo_generado_a_mano_no_pide_revision(): void
    {
        $this->actingAs(Cuenta::superAdmin(), 'web')
            ->post('/admin/aforo/generar', ['desde' => '2026-10-15', 'hasta' => '2026-10-31']);

        $this->assertSame(17, AforoDiario::count());
        $this->assertSame(0, AforoDiario::pendientesDeRevision()->count());
        $this->assertStringNotContainsString('automáticamente', $this->avisos());
    }
}
