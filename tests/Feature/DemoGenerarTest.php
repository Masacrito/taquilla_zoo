<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Cuenta;
use App\Models\ErrorSistema;
use App\Models\Pago;
use App\Models\Rubro;
use App\Services\Demo\GeneradorDemoService;
use App\Services\Reporte\CorteIngresosService;
use App\Services\Reporte\TableroService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El generador de datos de demostración.
 *
 * Una sola corrida de un mes por prueba: recorre el flujo real y tarda.
 */
class DemoGenerarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);
        $this->seed(CatalogosSeeder::class);

        // Un miércoles a media tarde: hay visitas de hoy ya ocurridas y por ocurrir.
        Carbon::setTestNow('2026-10-14 13:00:00');
    }

    private function generar(): void
    {
        $this->artisan('taquilla:demo-generar', ['--meses' => 1, '--force' => true])->assertSuccessful();
    }

    public function test_genera_una_historia_coherente_con_las_tarifas_oficiales(): void
    {
        // El transporte de verdad (el de pruebas guarda lo enviado en memoria),
        // tomado antes de que el generador ponga su doble.
        $transporte = app('mailer')->getSymfonyTransport();

        $this->generar();

        // ── Solo las tarifas oficiales, a su precio ──
        $vendido = CompraDetalle::select('rubro_nombre_snap', 'precio_centavos_snap')->distinct()->get()
            ->pluck('precio_centavos_snap', 'rubro_nombre_snap')->all();

        ksort($vendido);
        $oficiales = DemoSeeder::TARIFAS_OFICIALES;
        ksort($oficiales);

        $this->assertSame($oficiales, $vendido);
        $this->assertSame(3, Rubro::count(), 'No inventa tarifas.');

        // ── Pasó por todos los desenlaces ──
        $porEstado = Compra::selectRaw('estado, COUNT(*) AS n')->groupBy('estado')->pluck('n', 'estado');

        foreach ([Compra::UTILIZADA, Compra::ACCESO_PARCIAL, Compra::PAGADA, Compra::EXPIRADA,
                  Compra::CANCELADA, Compra::REEMBOLSADA] as $estado) {
            $this->assertGreaterThan(0, $porEstado[$estado] ?? 0, "Sin compras en estado {$estado}.");
        }

        $this->assertGreaterThan(0, Compra::whereNotNull('correo_invitado')->count(), 'Sin compras de invitado.');
        $this->assertGreaterThan(0, Pago::where('estado', Pago::RECHAZADO)->count());
        $this->assertGreaterThan(0, Acceso::where('resultado', Acceso::RECHAZADO)->count());
        $this->assertGreaterThan(0, ErrorSistema::pendientes()->count());

        // ── Coherente ──
        $this->assertSame(0, Compra::where('fecha_compra', '>', now())->count(), 'Nada fechado en el futuro.');
        $this->assertSame(0, Acceso::where('escaneado_en', '>', now())->count());
        $this->assertSame(
            Compra::count(),
            (int) substr(Compra::orderByDesc('id')->value('folio'), -6),
            'Los folios son consecutivos y sin huecos.',
        );

        // Lo utilizado entró completo, y entró el día de su visita.
        $this->assertSame(0, Compra::where('estado', Compra::UTILIZADA)->whereColumn('pases_usados', '!=', 'pases_total')->count());

        // ── Y ni un correo ──
        $this->assertCount(0, $transporte->messages(), 'No debe salir ningún correo.');
        $this->assertSame(0, DB::table('jobs')->count(), 'Ni quedar encolado para después.');

        // Los reportes lo leen: hay ingresos en el periodo.
        $corte = app(CorteIngresosService::class)->generar('2026-09-14', '2026-10-14', CorteIngresosService::POR_COMPRA);
        $this->assertGreaterThan(0, $corte['resumen']['total_centavos']);

        // Y la gráfica semanal del tablero reparte la venta por día. Con
        // CAST(... AS DATE) SQLite devolvía el año y la semana salía en ceros.
        $semana = app(TableroService::class)->generar()['semana'];
        $this->assertGreaterThan(0, $semana['total']);
        $this->assertGreaterThan(1, collect($semana['dias'])->where('centavos', '>', 0)->count());
    }

    public function test_limpiar_deja_la_base_como_estaba(): void
    {
        $this->generar();
        $this->assertGreaterThan(0, Compra::count());

        $this->artisan('taquilla:demo-generar', ['--limpiar' => true, '--force' => true])->assertSuccessful();

        $this->assertSame(0, Compra::withTrashed()->count());
        $this->assertSame(0, Pago::count());
        $this->assertSame(0, Acceso::count());
        $this->assertSame(0, Cliente::withTrashed()->count());
        $this->assertSame(0, ErrorSistema::count());
        $this->assertNull(Cuenta::withTrashed()->where('username', GeneradorDemoService::OPERADOR)->first());
        $this->assertSame(0, (int) DB::table('folios')->value('ultimo'), 'El folio vuelve a empezar.');
    }

    public function test_no_genera_dos_veces_ni_corre_en_produccion(): void
    {
        $this->generar();
        $compras = Compra::count();

        $this->artisan('taquilla:demo-generar', ['--meses' => 1, '--force' => true])->assertFailed();
        $this->assertSame($compras, Compra::count());

        $this->app['env'] = 'production';

        $this->artisan('taquilla:demo-generar', ['--limpiar' => true, '--force' => true])->assertFailed();
        $this->assertSame($compras, Compra::count());
    }
}
