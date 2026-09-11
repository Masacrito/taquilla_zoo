<?php

namespace Tests\Feature;

use App\Mail\ErrorDelSistema;
use App\Models\Cuenta;
use App\Models\ErrorSistema;
use App\Models\Usuario;
use App\Services\Auditoria\RegistroErroresService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErroresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);

        // El seeder deja al Super Admin con un correo de relleno. Para las
        // pruebas de aviso se le pone uno que sí pasa el filtro.
        Usuario::where('id_usuario', Cuenta::SUPER_ADMIN_ID)
            ->update(['email' => 'superadmin@semahn.chiapas.gob.mx']);
    }

    private function servicio(): RegistroErroresService
    {
        return app(RegistroErroresService::class);
    }

    private function taquillero(): Cuenta
    {
        $usuario = Usuario::create(['nombre' => 'Cajero', 'puesto' => 'Taquilla']);

        return Cuenta::create([
            'username'   => 'cajero_errores',
            'password'   => Hash::make('secreto123'),
            'estado'     => 'activo',
            'id_usuario' => $usuario->id_usuario,
            'id_rol'     => 2,
        ]);
    }

    // ═══ Qué se registra y qué no ═══

    public function test_una_excepcion_no_controlada_queda_registrada(): void
    {
        $this->servicio()->registrar(new RuntimeException('Se cayó algo'));

        $error = ErrorSistema::firstOrFail();

        $this->assertSame(RuntimeException::class, $error->clase);
        $this->assertSame('Se cayó algo', $error->mensaje);
        $this->assertSame(1, $error->ocurrencias);
        $this->assertNotNull($error->traza);
    }

    /**
     * Lo que hace usable la tabla. Sin agrupar, una excepción en bucle mete
     * miles de filas idénticas y la pantalla queda inservible.
     */
    public function test_el_mismo_fallo_repetido_suma_en_vez_de_duplicar(): void
    {
        $fallo = new RuntimeException('Se repite');

        foreach (range(1, 5) as $vez) {
            $this->servicio()->registrar($fallo);
        }

        $this->assertSame(1, ErrorSistema::count(), 'Cinco repeticiones deben ser una sola fila.');
        $this->assertSame(5, ErrorSistema::first()->ocurrencias);
    }

    public function test_un_404_no_se_registra(): void
    {
        // La mayoría son bots buscando /wp-admin. Registrarlos ahogaría lo
        // que sí importa.
        $this->servicio()->registrar(new NotFoundHttpException('No existe'));

        $this->assertSame(0, ErrorSistema::count());
    }

    public function test_bajando_el_codigo_minimo_si_se_registran_los_404(): void
    {
        config(['taquilla.errores.codigo_minimo' => 400]);

        $this->servicio()->registrar(new NotFoundHttpException('No existe'));

        $this->assertSame(1, ErrorSistema::count());
    }

    /**
     * LA PRUEBA QUE MÁS IMPORTA.
     *
     * El servicio corre dentro del manejador de errores de Laravel. Si
     * revienta aquí, el visitante se queda sin pantalla y sin respuesta: un
     * fallo al reportar un fallo tumbaría el sitio entero.
     */
    public function test_registrar_nunca_lanza_excepcion_aunque_la_base_falle(): void
    {
        Schema::drop('errores');

        $resultado = $this->servicio()->registrar(new RuntimeException('La base no está'));

        $this->assertNull($resultado, 'Sin tabla no hay registro, pero tampoco excepción.');
    }

    // ═══ Aviso al Super Admin ═══

    public function test_se_avisa_al_super_admin_la_primera_vez(): void
    {
        Mail::fake();

        $this->servicio()->registrar(new RuntimeException('Algo nuevo'));

        Mail::assertQueued(ErrorDelSistema::class, function ($correo) {
            return $correo->hasTo('superadmin@semahn.chiapas.gob.mx');
        });
    }

    public function test_el_mismo_fallo_no_vuelve_a_avisar_de_inmediato(): void
    {
        Mail::fake();
        $fallo = new RuntimeException('En bucle');

        foreach (range(1, 10) as $vez) {
            $this->servicio()->registrar($fallo);
        }

        // Sin freno, una excepción en bucle inunda el buzón justo cuando más
        // falta leerlo.
        Mail::assertQueuedCount(1);
    }

    public function test_no_se_avisa_si_el_super_admin_sigue_con_el_correo_de_relleno(): void
    {
        Mail::fake();

        Usuario::where('id_usuario', Cuenta::SUPER_ADMIN_ID)
            ->update(['email' => 'admin@example.com']);

        $this->servicio()->registrar(new RuntimeException('Nadie lo va a leer'));

        Mail::assertNothingQueued();
        $this->assertSame(1, ErrorSistema::count(), 'El fallo se registra aunque no haya a quién avisarle.');
    }

    /**
     * Guarda el arreglo de zona horaria.
     *
     * Laravel serializa las fechas sin offset y Postgres interpreta ese texto
     * en la zona de la SESIÓN, que por omisión es UTC. Sin esta configuración,
     * un `now()` de las 16:00 en Chiapas quedaba guardado como las 16:00 UTC:
     * seis horas en el futuro. Eso desfasa la expiración de compras a los 15
     * minutos, la vigencia del QR y los cortes por día.
     *
     * Solo se comprueba la configuración: SQLite —la base de las pruebas— no
     * maneja zonas horarias, así que el comportamiento real únicamente se ve
     * contra Postgres.
     */
    public function test_la_conexion_de_postgres_va_en_la_misma_zona_que_la_aplicacion(): void
    {
        $this->assertSame(
            config('app.timezone'),
            config('database.connections.pgsql.timezone'),
            'La sesión de Postgres debe ir en la zona de la aplicación, o las fechas se guardan corridas.',
        );
    }

    // ═══ Pantallas de error ═══

    public function test_el_404_muestra_la_pantalla_del_zoomat(): void
    {
        $this->get('/esta-ruta-no-existe-en-ningun-lado')
            ->assertNotFound()
            ->assertSee('Esta página no existe')
            ->assertSee('Miguel Álvarez del Toro');
    }

    /**
     * El 419 es el más frecuente en un portal de compra: la gente deja la
     * pestaña abierta y vuelve al rato. Sin pantalla propia veían el «Page
     * Expired» de Laravel, que no le dice nada a nadie.
     */
    public function test_la_pantalla_de_sesion_expirada_explica_que_hacer(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('Tu sesión expiró', $html);
        $this->assertStringContainsString('No se generó ningún cobro', $html);
    }

    public function test_la_pantalla_de_error_del_servidor_advierte_de_no_pagar_dos_veces(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('NO vuelvas a pagar', $html);
    }

    // ═══ La pantalla del panel ═══

    public function test_el_admin_ve_los_fallos(): void
    {
        $this->servicio()->registrar(new RuntimeException('Para la lista'));

        $this->actingAs(Cuenta::superAdmin(), 'web')
            ->get('/admin/errores')
            ->assertOk()
            ->assertSee('RuntimeException')
            ->assertSee('Para la lista');
    }

    public function test_taquilla_no_alcanza_los_fallos(): void
    {
        // `EnsurePermission` devuelve al dashboard propio en vez de un 403,
        // igual que con rubros, catálogos y la bitácora.
        $this->actingAs($this->taquillero(), 'web')
            ->get('/admin/errores')
            ->assertRedirect(route('taquilla.dashboard'));

        $this->assertSame(0, ErrorSistema::count(), 'Ni siquiera debe llegar a consultar.');
    }

    public function test_marcar_como_atendido_deja_constancia_de_quien_fue(): void
    {
        $this->servicio()->registrar(new RuntimeException('Ya lo revisé'));
        $error = ErrorSistema::firstOrFail();
        $admin = Cuenta::superAdmin();

        $this->actingAs($admin, 'web')
            ->put(route('admin.errores.atender', $error))
            ->assertRedirect();

        $error->refresh();

        $this->assertNotNull($error->atendido_en);
        $this->assertSame($admin->id_cuenta, $error->atendido_por);
        $this->assertTrue($error->estaAtendido());
    }

    public function test_los_atendidos_salen_de_la_lista_de_pendientes(): void
    {
        $this->servicio()->registrar(new RuntimeException('Atendido'));
        $error = ErrorSistema::firstOrFail();
        $error->update(['atendido_en' => now(), 'atendido_por' => Cuenta::superAdmin()->id_cuenta]);

        $this->assertSame(0, ErrorSistema::pendientes()->count());

        $this->actingAs(Cuenta::superAdmin(), 'web')
            ->get('/admin/errores')
            ->assertOk()
            ->assertDontSee('Atendido');
    }
}
