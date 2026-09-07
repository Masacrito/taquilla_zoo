<?php

namespace Tests\Feature;

use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\Movimiento;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminComprasTest extends TestCase
{
    use RefreshDatabase;

    private Rubro $rubro;
    private string $fechaVisita;

    protected function setUp(): void
    {
        parent::setUp();
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

        $this->fechaVisita = Carbon::parse('next tuesday')->toDateString();
        AforoDiario::create(['fecha' => $this->fechaVisita]);
    }

    private function admin(): Cuenta
    {
        return Cuenta::where('username', 'admin')->firstOrFail();
    }

    private function taquillero(): Cuenta
    {
        $usuario = Usuario::create(['nombre' => 'Cajero', 'puesto' => 'Taquilla']);

        return Cuenta::create([
            'username'   => 'cajero1',
            'password'   => Hash::make('secreto123'),
            'estado'     => 'activo',
            'id_usuario' => $usuario->id_usuario,
            'id_rol'     => 2,
        ]);
    }

    private function cliente(): Cliente
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

    private function compra(int $pases = 4): Compra
    {
        $cotizacion = app(CotizarCompraService::class)->cotizar(
            [['id_rubro' => $this->rubro->id, 'cant_hombre' => $pases, 'cant_mujer' => 0]],
            $this->fechaVisita,
        );

        return app(RegistrarCompraService::class)->registrar(
            $this->cliente(), $cotizacion, $this->fechaVisita,
        );
    }

    // ═══ Acceso ═══

    public function test_el_admin_ve_las_pantallas_de_compras_y_visitantes(): void
    {
        $this->compra();

        $this->actingAs($this->admin(), 'web')->get('/admin/compras')->assertOk();
        $this->actingAs($this->admin(), 'web')->get('/admin/clientes')->assertOk();
    }

    public function test_taquilla_no_entra_a_compras_ni_visitantes(): void
    {
        $cajero = $this->taquillero();

        $this->actingAs($cajero, 'web')->get('/admin/compras')
            ->assertRedirect(route('taquilla.dashboard'));
        $this->actingAs($cajero, 'web')->get('/admin/clientes')
            ->assertRedirect(route('taquilla.dashboard'));
    }

    public function test_el_listado_muestra_folio_y_visitante(): void
    {
        $compra = $this->compra();

        $this->actingAs($this->admin(), 'web')->get('/admin/compras')
            ->assertOk()
            ->assertSee($compra->folio)
            ->assertSee('ana@example.com');
    }

    public function test_la_ficha_del_visitante_lista_sus_compras(): void
    {
        $compra = $this->compra();

        $this->actingAs($this->admin(), 'web')
            ->get(route('admin.clientes.ver', $compra->cliente))
            ->assertOk()
            ->assertSee($compra->folio)
            ->assertSee('Ana Pérez');
    }

    // ═══ Cancelación ═══

    public function test_cancelar_conserva_el_folio(): void
    {
        $compra = $this->compra(4);
        $compra->update(['estado' => Compra::PAGADA]);

        $this->actingAs($this->admin(), 'web')
            ->put(route('admin.compras.cancelar', $compra), ['motivo' => 'Solicitud del visitante'])
            ->assertRedirect();

        $compra->refresh();
        $this->assertSame(Compra::CANCELADA, $compra->estado);
        $this->assertNotNull(Compra::find($compra->id), 'El folio debe conservarse.');
    }

    /**
     * Cancelar no deshace los accesos ya ocurridos: si tres personas entraron,
     * el corte y la estadística tienen que seguir contándolas.
     */
    public function test_cancelar_no_borra_los_pases_ya_usados(): void
    {
        $compra = $this->compra(4);
        $compra->update(['estado' => Compra::PAGADA]);
        Compra::consumirPases($compra->id, 3);

        $this->actingAs($this->admin(), 'web')
            ->put(route('admin.compras.cancelar', $compra), ['motivo' => 'Grupo incompleto'])
            ->assertRedirect();

        $compra->refresh();
        $this->assertSame(Compra::CANCELADA, $compra->estado);
        $this->assertSame(3, $compra->pases_usados, 'Los accesos ya ocurridos no se deshacen.');
        $this->assertSame(4, $compra->pases_total);
    }

    public function test_no_se_puede_cancelar_una_compra_ya_expirada(): void
    {
        $compra = $this->compra();
        $compra->update(['estado' => Compra::EXPIRADA]);

        $this->actingAs($this->admin(), 'web')
            ->put(route('admin.compras.cancelar', $compra), ['motivo' => 'Lo que sea'])
            ->assertSessionHas('error');

        $this->assertSame(Compra::EXPIRADA, $compra->refresh()->estado);
    }

    public function test_cancelar_exige_motivo(): void
    {
        $compra = $this->compra();
        $compra->update(['estado' => Compra::PAGADA]);

        $this->actingAs($this->admin(), 'web')
            ->put(route('admin.compras.cancelar', $compra), ['motivo' => 'no'])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(Compra::PAGADA, $compra->refresh()->estado);
    }

    public function test_la_cancelacion_queda_en_la_bitacora(): void
    {
        $compra = $this->compra();
        $compra->update(['estado' => Compra::PAGADA]);

        $this->actingAs($this->admin(), 'web')
            ->put(route('admin.compras.cancelar', $compra), ['motivo' => 'Solicitud del visitante']);

        $mov = Movimiento::where('tabla', 'compras')
            ->where('accion', 'UPDATE')
            ->where('registro_id', (string) $compra->id)
            ->latest('id_movimiento')
            ->first();

        $this->assertNotNull($mov);
        $this->assertSame('Solicitud del visitante', $mov->detalles['motivo']);
    }

    public function test_taquilla_no_puede_cancelar(): void
    {
        $compra = $this->compra();
        $compra->update(['estado' => Compra::PAGADA]);

        $this->actingAs($this->taquillero(), 'web')
            ->put(route('admin.compras.cancelar', $compra), ['motivo' => 'Sin permiso'])
            ->assertRedirect(route('taquilla.dashboard'));

        $this->assertSame(Compra::PAGADA, $compra->refresh()->estado);
    }
}
