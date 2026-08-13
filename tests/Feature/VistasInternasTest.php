<?php

namespace Tests\Feature;

use App\Models\Cuenta;
use App\Models\Usuario;
use Database\Seeders\AuthSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Humo de renderizado: que las vistas compilen y traigan la identidad
 * gráfica del manual (brief §8).
 */
class VistasInternasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);
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

    public function test_el_login_del_personal_renderiza_con_la_identidad_grafica(): void
    {
        $this->get('/acceso')
            ->assertOk()
            ->assertSee('Acceso para personal')
            ->assertSee('nombre de usuario')   // no debe confundirse con el portal
            ->assertSee('titulo', false);      // utilidad tipográfica del manual
    }

    public function test_dashboard_admin_renderiza(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Panel administrador')
            ->assertSee('Super Admin');
    }

    public function test_dashboard_taquilla_renderiza_y_muestra_el_horario(): void
    {
        $this->actingAs($this->taquillero(), 'web')
            ->get('/taquilla/dashboard')
            ->assertOk()
            ->assertSee('Panel taquilla')
            ->assertSee('Lunes cerrado');
    }

    public function test_listado_de_usuarios_renderiza(): void
    {
        $this->taquillero();

        $this->actingAs($this->admin(), 'web')
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Gestión de usuarios')
            ->assertSee('cajero1')
            ->assertSee('Administrador');
    }

    public function test_taquilla_no_entra_al_dashboard_de_admin(): void
    {
        $this->actingAs($this->taquillero(), 'web')
            ->get('/admin/dashboard')
            ->assertRedirect(route('taquilla.dashboard'));
    }

    public function test_taquilla_no_entra_a_la_gestion_de_usuarios(): void
    {
        $this->actingAs($this->taquillero(), 'web')
            ->get('/admin/users')
            ->assertRedirect(route('taquilla.dashboard'));
    }
}
