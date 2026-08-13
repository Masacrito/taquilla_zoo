<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Usuario;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Brief §10.6 — Aislamiento de guards.
 *
 * Las dos poblaciones del sistema no se cruzan: un visitante nunca alcanza
 * el panel interno, y una cuenta interna nunca opera como visitante.
 */
class AislamientoGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'correo'               => 'visitante@example.com',
            'password'             => Hash::make('secreto123'),
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez López',
            'fecha_nacimiento'     => '1990-05-14',
            'genero'               => 'Femenino',
            'telefono'             => '9611234567',
            'correo_verificado_en' => now(),
        ]);
    }

    private function cuentaAdmin(): Cuenta
    {
        return Cuenta::where('username', 'admin')->firstOrFail();
    }

    public function test_cliente_autenticado_recibe_403_en_admin_dashboard(): void
    {
        $this->actingAs($this->cliente(), 'cliente')
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_cliente_autenticado_recibe_403_en_gestion_de_usuarios(): void
    {
        $this->actingAs($this->cliente(), 'cliente')
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_cliente_autenticado_recibe_403_en_taquilla(): void
    {
        $this->actingAs($this->cliente(), 'cliente')
            ->get('/taquilla/dashboard')
            ->assertForbidden();
    }

    public function test_administrador_si_alcanza_su_panel(): void
    {
        $this->actingAs($this->cuentaAdmin(), 'web')
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_visitante_anonimo_es_enviado_al_login_no_a_403(): void
    {
        // Sin sesión en ningún guard: el flujo normal es redirigir al login
        // del personal (/acceso), no 403. La raíz `/` es el portal público.
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_cuenta_interna_desactivada_pierde_la_sesion(): void
    {
        $cuenta = $this->cuentaAdmin();
        $cuenta->update(['estado' => 'inactivo']);

        $this->actingAs($cuenta, 'web')
            ->get('/admin/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest('web');
    }

    public function test_los_guards_no_comparten_sesion(): void
    {
        $this->actingAs($this->cliente(), 'cliente');

        $this->assertAuthenticated('cliente');
        $this->assertGuest('web');
    }

    public function test_el_cliente_no_tiene_rol_ni_permisos(): void
    {
        $cliente = $this->cliente();

        $this->assertFalse(method_exists($cliente, 'tienePermiso'));
        $this->assertFalse(method_exists($cliente, 'isAdmin'));
    }
}
