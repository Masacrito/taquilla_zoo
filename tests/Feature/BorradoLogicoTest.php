<?php

namespace Tests\Feature;

use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Models\Usuario;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Brief §4.6 — Nada se borra.
 */
class BorradoLogicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
    }

    private function superAdmin(): Cuenta
    {
        return Cuenta::where('username', 'admin')->firstOrFail();
    }

    private function crearCuentaTaquilla(string $username = 'cajero1'): Cuenta
    {
        $usuario = Usuario::create(['nombre' => 'Cajero de Prueba', 'puesto' => 'Taquilla']);

        return Cuenta::create([
            'username'   => $username,
            'password'   => Hash::make('secreto123'),
            'estado'     => 'activo',
            'id_usuario' => $usuario->id_usuario,
            'id_rol'     => 2,
        ]);
    }

    public function test_eliminar_conserva_el_registro_en_la_base(): void
    {
        $cuenta = $this->crearCuentaTaquilla();

        $this->actingAs($this->superAdmin(), 'web')
            ->delete("/admin/users/{$cuenta->id_cuenta}")
            ->assertRedirect();

        // Fuera de las consultas normales...
        $this->assertNull(Cuenta::find($cuenta->id_cuenta));
        // ...pero la fila sigue ahí.
        $this->assertNotNull(Cuenta::withTrashed()->find($cuenta->id_cuenta));
        $this->assertNotNull(Usuario::withTrashed()->find($cuenta->id_usuario));
    }

    public function test_el_usuario_asociado_tambien_se_borra_logicamente(): void
    {
        $cuenta = $this->crearCuentaTaquilla();

        $this->actingAs($this->superAdmin(), 'web')
            ->delete("/admin/users/{$cuenta->id_cuenta}");

        $this->assertNull(Usuario::find($cuenta->id_usuario));
        $this->assertNotNull(Usuario::withTrashed()->find($cuenta->id_usuario)->deleted_at);
    }

    public function test_una_cuenta_eliminada_no_puede_autenticarse(): void
    {
        $cuenta = $this->crearCuentaTaquilla();

        $this->actingAs($this->superAdmin(), 'web')
            ->delete("/admin/users/{$cuenta->id_cuenta}");

        // Salir de la sesión del admin que hizo el borrado: si no, el
        // assertGuest de abajo lo detectaría a él y no probaría nada.
        auth('web')->logout();
        $this->flushSession();

        $this->post('/acceso', ['username' => 'cajero1', 'password' => 'secreto123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest('web');
    }

    public function test_la_eliminacion_queda_en_la_bitacora(): void
    {
        $cuenta = $this->crearCuentaTaquilla();

        $this->actingAs($this->superAdmin(), 'web')
            ->delete("/admin/users/{$cuenta->id_cuenta}");

        $mov = Movimiento::where('tabla', 'cuentas')
            ->where('accion', 'DELETE')
            ->where('registro_id', (string) $cuenta->id_cuenta)
            ->first();

        $this->assertNotNull($mov);
        $this->assertSame('cajero1', $mov->detalles['username']);
    }

    public function test_el_username_no_se_reutiliza(): void
    {
        $cuenta = $this->crearCuentaTaquilla();

        $this->actingAs($this->superAdmin(), 'web')
            ->delete("/admin/users/{$cuenta->id_cuenta}");

        $this->actingAs($this->superAdmin(), 'web')
            ->post('/admin/users', [
                'nombre'   => 'Otro Cajero',
                'username' => 'cajero1',
                'password' => 'secreto123',
                'id_rol'   => 2,
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_no_puedes_eliminarte_a_ti_mismo(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'web')
            ->delete("/admin/users/{$admin->id_cuenta}");

        $this->assertNotNull(Cuenta::find($admin->id_cuenta));
    }
}
