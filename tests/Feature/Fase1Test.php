<?php

namespace Tests\Feature;

use App\Models\AforoDiario;
use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Models\Nacionalidad;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Models\Usuario;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Fase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);
        $this->seed(CatalogosSeeder::class);
    }

    private function admin(): Cuenta
    {
        return Cuenta::where('username', 'admin')->firstOrFail();
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

    private function datosRubro(array $sobrescribir = []): array
    {
        return array_merge([
            'tipo'               => 'Adulto nacional',
            'id_nacionalidad'    => Nacionalidad::where('nombre', 'NACIONAL')->value('id'),
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'ADULTO NACIONAL')->value('id'),
            'id_tipo_acceso'     => TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->value('id'),
            'precio'             => '40.00',
            'vigente_desde'      => now()->toDateString(),
            'activo'             => '1',
        ], $sobrescribir);
    }

    // ═══ Rubros: el dinero y la regla de GRATIS ═══

    public function test_el_precio_se_guarda_en_centavos_como_entero(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post('/admin/rubros', $this->datosRubro(['precio' => '40.50']))
            ->assertRedirect();

        $rubro = Rubro::firstOrFail();

        $this->assertSame(4050, $rubro->precio_centavos);
        $this->assertIsInt($rubro->precio_centavos);
        $this->assertSame('$40.50', $rubro->precioFormateado());
    }

    public function test_un_rubro_gratis_con_precio_es_rechazado(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post('/admin/rubros', $this->datosRubro([
                'id_tipo_acceso' => TipoAcceso::where('nombre', TipoAcceso::GRATIS)->value('id'),
                'precio'         => '25.00',
            ]))
            ->assertSessionHasErrors('precio');

        $this->assertSame(0, Rubro::count());
    }

    public function test_un_rubro_gratis_con_precio_cero_se_acepta(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post('/admin/rubros', $this->datosRubro([
                'tipo'           => 'Niño Pavón',
                'id_tipo_acceso' => TipoAcceso::where('nombre', TipoAcceso::GRATIS)->value('id'),
                'precio'         => '0',
            ]))
            ->assertSessionHasNoErrors();

        $rubro = Rubro::firstOrFail();
        $this->assertSame(0, $rubro->precio_centavos);
        $this->assertTrue($rubro->esGratis());
    }

    public function test_el_alta_de_rubro_queda_en_la_bitacora(): void
    {
        $this->actingAs($this->admin(), 'web')->post('/admin/rubros', $this->datosRubro());

        $this->assertTrue(
            Movimiento::where('tabla', 'rubros')->where('accion', 'CREATE')->exists()
        );
    }

    public function test_el_scope_vigentes_excluye_rubros_fuera_de_fecha(): void
    {
        $this->actingAs($this->admin(), 'web')->post('/admin/rubros', $this->datosRubro([
            'vigente_desde' => now()->subDays(10)->toDateString(),
            'vigente_hasta' => now()->subDay()->toDateString(),
        ]));

        $this->assertSame(0, Rubro::vigentes()->count());
        $this->assertSame(1, Rubro::vigentes(now()->subDays(5)->toDateString())->count());
    }

    // ═══ Calendario de operación: lunes cerrados ═══

    public function test_los_lunes_se_generan_cerrados(): void
    {
        $lunes = Carbon::parse('next monday')->startOfDay();

        $this->actingAs($this->admin(), 'web')->post('/admin/aforo/generar', [
            'desde' => $lunes->toDateString(),
            'hasta' => $lunes->copy()->addDays(6)->toDateString(),
        ])->assertRedirect();

        $this->assertTrue(AforoDiario::find($lunes->toDateString())->cerrado);
        $this->assertFalse(AforoDiario::find($lunes->copy()->addDay()->toDateString())->cerrado);
        $this->assertSame(7, AforoDiario::count());
    }

    public function test_generar_no_pisa_dias_existentes(): void
    {
        $martes = Carbon::parse('next tuesday')->toDateString();

        // Un martes cerrado a mano: generar no debe reabrirlo.
        AforoDiario::create([
            'fecha'         => $martes,
            'cerrado'       => true,
            'motivo_cierre' => 'Fumigación',
        ]);

        $this->actingAs($this->admin(), 'web')->post('/admin/aforo/generar', [
            'desde' => $martes,
            'hasta' => $martes,
        ]);

        $dia = AforoDiario::find($martes);
        $this->assertTrue($dia->cerrado);
        $this->assertSame('Fumigación', $dia->motivo_cierre);
    }

    public function test_la_pantalla_del_calendario_renderiza_los_dias(): void
    {
        $martes = Carbon::parse('next tuesday')->toDateString();
        $lunes  = Carbon::parse('next monday')->toDateString();

        AforoDiario::create(['fecha' => $martes]);
        AforoDiario::create(['fecha' => $lunes, 'cerrado' => true, 'motivo_cierre' => 'Lunes: el zoológico no abre.']);

        $this->actingAs($this->admin(), 'web')
            ->get('/admin/aforo?desde=' . $lunes . '&hasta=' . Carbon::parse($lunes)->addWeek()->toDateString())
            ->assertOk()
            ->assertSee('Calendario de operación')
            ->assertSee('abierto')
            ->assertSee('cerrado')
            ->assertSee('Lunes: el zoológico no abre.')
            ->assertDontSee('Cupo');
    }

    public function test_se_puede_cerrar_y_reabrir_un_dia(): void
    {
        $fecha = Carbon::parse('next tuesday')->toDateString();
        AforoDiario::create(['fecha' => $fecha]);

        $this->actingAs($this->admin(), 'web')
            ->put("/admin/aforo/{$fecha}", ['cerrado' => 1, 'motivo_cierre' => 'Contingencia'])
            ->assertSessionHas('success');

        $dia = AforoDiario::find($fecha);
        $this->assertTrue($dia->cerrado);
        $this->assertSame('Contingencia', $dia->motivo_cierre);

        $this->actingAs($this->admin(), 'web')
            ->put("/admin/aforo/{$fecha}", [])
            ->assertSessionHas('success');

        $dia = AforoDiario::find($fecha);
        $this->assertFalse($dia->cerrado);
        $this->assertNull($dia->motivo_cierre, 'Al reabrir, el motivo del cierre debe limpiarse.');
    }

    // ═══ Permisos de las rutas nuevas ═══

    public function test_taquilla_no_entra_a_rubros_ni_catalogos_ni_aforo_ni_bitacora(): void
    {
        $cajero = $this->taquillero();

        foreach (['/admin/rubros', '/admin/catalogos', '/admin/aforo', '/admin/bitacora'] as $ruta) {
            $this->actingAs($cajero, 'web')
                ->get($ruta)
                ->assertRedirect(route('taquilla.dashboard'));
        }
    }

    public function test_el_admin_ve_todas_las_pantallas_nuevas(): void
    {
        foreach (['/admin/rubros', '/admin/catalogos', '/admin/aforo', '/admin/bitacora'] as $ruta) {
            $this->actingAs($this->admin(), 'web')->get($ruta)->assertOk();
        }
    }

    public function test_los_catalogos_se_desactivan_no_se_borran(): void
    {
        $nacionalidad = Nacionalidad::where('nombre', 'NACIONAL')->firstOrFail();

        $this->actingAs($this->admin(), 'web')
            ->put("/admin/catalogos/nacionalidades/{$nacionalidad->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($nacionalidad->fresh()->activo);
        $this->assertNotNull(Nacionalidad::find($nacionalidad->id));
    }

    public function test_los_catalogos_se_sembraron_completos(): void
    {
        $this->assertSame(32, \App\Models\Estado::count());
        $this->assertSame(124, \App\Models\Municipio::count());
        $this->assertSame(2, Nacionalidad::count());
        $this->assertSame(5, Subnacionalidad::count());   // las 4 originales + TERCERA EDAD NACIONAL
        $this->assertSame(2, TipoAcceso::count());
    }
}
