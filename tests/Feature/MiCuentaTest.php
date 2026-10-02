<?php

namespace Tests\Feature;

use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Models\Usuario;
use App\Services\Reporte\TableroService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «Mi cuenta» y la jerarquía de la gestión de usuarios.
 *
 * Dos huecos que iban juntos: nadie podía cambiar su propia contraseña (ni la
 * inicial del Super Admin), y la pantalla de usuarios ofrecía botones sobre
 * cuentas que el servidor luego se negaba a tocar.
 */
class MiCuentaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'claveInicial123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);

        Cuenta::superAdmin()->forceFill(['password' => Hash::make(self::CLAVE)])->save();
    }

    private function cuenta(string $username, int $idRol): Cuenta
    {
        $usuario = Usuario::create(['nombre' => ucfirst($username), 'puesto' => 'Prueba']);

        return Cuenta::create([
            'username'   => $username,
            'password'   => Hash::make(self::CLAVE),
            'estado'     => 'activo',
            'id_usuario' => $usuario->id_usuario,
            'id_rol'     => $idRol,
        ]);
    }

    private function cambiarPassword(Cuenta $cuenta, string $actual, string $nueva)
    {
        return $this->actingAs($cuenta, 'web')
            ->from('/panel/mi-cuenta')
            ->put('/panel/mi-cuenta/password', [
                'password_actual'       => $actual,
                'password'              => $nueva,
                'password_confirmation' => $nueva,
            ]);
    }

    // ═══ Mi cuenta ═══

    public function test_el_super_admin_cambia_su_propia_contrasena(): void
    {
        $super = Cuenta::superAdmin();

        $this->cambiarPassword($super, self::CLAVE, 'otraClaveSegura42')
            ->assertRedirect('/panel/mi-cuenta')
            ->assertSessionHasNoErrors();

        $super->refresh();
        $this->assertTrue(Hash::check('otraClaveSegura42', $super->password));
        $this->assertNotNull($super->password_cambiado_en);

        // Queda en la bitácora que cambió, pero nunca la clave.
        $movimiento = Movimiento::where('tabla', 'cuentas')->latest('id_movimiento')->first();
        $this->assertSame(['password_cambiado' => true], $movimiento->detalles);
    }

    public function test_sin_la_contrasena_actual_no_se_cambia(): void
    {
        $super = Cuenta::superAdmin();

        $this->cambiarPassword($super, 'no-es-esta', 'otraClaveSegura42')
            ->assertSessionHasErrors('password_actual');

        $this->assertTrue(Hash::check(self::CLAVE, $super->refresh()->password));
    }

    public function test_una_contrasena_debil_se_rechaza(): void
    {
        $this->cambiarPassword(Cuenta::superAdmin(), self::CLAVE, 'corta1')
            ->assertSessionHasErrors('password');
    }

    public function test_taquilla_tambien_cambia_la_suya_y_solo_la_suya(): void
    {
        $cajero = $this->cuenta('cajero1', 2);

        $this->cambiarPassword($cajero, self::CLAVE, 'claveDelCajero77')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('claveDelCajero77', $cajero->refresh()->password));
        $this->assertTrue(Hash::check(self::CLAVE, Cuenta::superAdmin()->password), 'La del Super Admin no se tocó.');
    }

    public function test_el_super_admin_pone_su_correo_real(): void
    {
        $this->actingAs(Cuenta::superAdmin(), 'web')
            ->put('/panel/mi-cuenta', ['nombre' => 'Super Admin', 'email' => 'sistemas@semahn.chiapas.gob.mx'])
            ->assertSessionHasNoErrors();

        $this->assertSame('sistemas@semahn.chiapas.gob.mx', Cuenta::superAdmin()->usuario->email);
    }

    public function test_el_tablero_avisa_mientras_siga_la_contrasena_inicial(): void
    {
        $avisos = fn () => collect(app(TableroService::class)->generar()['avisos'])->pluck('texto')->implode(' ');

        $this->assertStringContainsString('contraseña inicial', $avisos());

        $this->cambiarPassword(Cuenta::superAdmin(), self::CLAVE, 'otraClaveSegura42');

        $this->assertStringNotContainsString('contraseña inicial', $avisos());
    }

    // ═══ Jerarquía en la gestión de usuarios ═══

    public function test_un_administrador_comun_no_ve_controles_sobre_otros_administradores(): void
    {
        $admin = $this->cuenta('admin2', Cuenta::ROL_ADMINISTRADOR);
        $otro  = $this->cuenta('admin3', Cuenta::ROL_ADMINISTRADOR);
        $super = Cuenta::superAdmin();
        $cajero = $this->cuenta('cajero1', 2);

        $this->actingAs($admin, 'web')
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Cuenta protegida')
            ->assertDontSee(route('admin.users.update', $otro->id_cuenta), false)
            ->assertDontSee(route('admin.users.destroy', $super->id_cuenta), false)
            ->assertDontSee(route('admin.users.update-role', $otro->id_cuenta), false)
            ->assertDontSee(route('admin.users.update-permissions', $otro->id_cuenta), false)
            // A Taquilla sí la administra.
            ->assertSee(route('admin.users.update', $cajero->id_cuenta), false);
    }

    public function test_el_super_admin_si_ve_los_controles_sobre_un_administrador(): void
    {
        $admin = $this->cuenta('admin2', Cuenta::ROL_ADMINISTRADOR);

        $this->actingAs(Cuenta::superAdmin(), 'web')
            ->get('/admin/users')
            ->assertOk()
            ->assertSee(route('admin.users.update', $admin->id_cuenta), false)
            ->assertSee(route('admin.users.destroy', $admin->id_cuenta), false);
    }

    /** Que la pantalla no lo ofrezca no basta: la petición directa también se rechaza. */
    public function test_un_administrador_comun_no_modifica_a_otro_ni_crea_administradores(): void
    {
        $admin = $this->cuenta('admin2', Cuenta::ROL_ADMINISTRADOR);
        $otro  = $this->cuenta('admin3', Cuenta::ROL_ADMINISTRADOR);

        $this->actingAs($admin, 'web');

        $this->put("/admin/users/{$otro->id_cuenta}", ['nombre' => 'Hackeado', 'password' => 'nuevaClave99']);
        $this->delete("/admin/users/{$otro->id_cuenta}");
        $this->put('/admin/users/' . Cuenta::superAdmin()->id_cuenta . '/toggle-status');
        $this->post('/admin/users', [
            'nombre' => 'Nuevo Admin', 'username' => 'admin9', 'password' => 'secreto123',
            'id_rol' => Cuenta::ROL_ADMINISTRADOR,
        ]);

        $otro->refresh();
        $this->assertSame('Admin3', $otro->usuario->nombre);
        $this->assertTrue(Hash::check(self::CLAVE, $otro->password));
        $this->assertSame('activo', Cuenta::superAdmin()->estado);
        $this->assertDatabaseMissing('cuentas', ['username' => 'admin9']);
    }
}
