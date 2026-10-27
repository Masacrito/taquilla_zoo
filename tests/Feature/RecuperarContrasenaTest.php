<?php

namespace Tests\Feature;

use App\Mail\RestablecerContrasena;
use App\Models\Cliente;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * «¿Olvidaste tu contraseña?» del portal (2026-10-27).
 */
class RecuperarContrasenaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        Mail::fake();
    }

    private function cliente(array $extra = []): Cliente
    {
        return Cliente::create([
            'correo'               => 'ana@example.com',
            'password'             => 'vieja12345',
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
            ...$extra,
        ]);
    }

    /** Pide el enlace y devuelve el token que viajó en el correo. */
    private function pedirEnlace(string $correo = 'ana@example.com'): string
    {
        $this->post('/recuperar', ['correo' => $correo])->assertSessionHas('success');

        $token = null;
        Mail::assertQueued(RestablecerContrasena::class, function ($mail) use (&$token) {
            $token = $mail->token;

            return $mail->hasTo('ana@example.com');
        });

        return $token;
    }

    public function test_el_enlace_permite_cambiar_la_contrasena(): void
    {
        $cliente = $this->cliente();
        $token   = $this->pedirEnlace('ANA@example.com ');

        $this->get("/recuperar/{$token}")->assertOk()->assertSee('Elige tu contraseña nueva');

        $this->post("/recuperar/{$token}", [
            'correo'                => 'ana@example.com',
            'password'              => 'nueva12345',
            'password_confirmation' => 'nueva12345',
        ])->assertRedirect(route('portal.ingresar'));

        $this->assertTrue(Auth::guard('cliente')->validate(['correo' => $cliente->correo, 'password' => 'nueva12345']));

        // El enlace es de un solo uso.
        $this->post("/recuperar/{$token}", [
            'correo'                => 'ana@example.com',
            'password'              => 'otra123456',
            'password_confirmation' => 'otra123456',
        ])->assertSessionHasErrors('correo');
    }

    public function test_no_revela_si_el_correo_existe(): void
    {
        $this->post('/recuperar', ['correo' => 'nadie@example.com'])->assertSessionHas('success');

        Mail::assertNothingQueued();
    }

    public function test_token_falso_o_correo_ajeno_no_cambian_nada(): void
    {
        $this->cliente();
        $token = $this->pedirEnlace();

        $this->post('/recuperar/inventado', [
            'correo' => 'ana@example.com', 'password' => 'nueva12345', 'password_confirmation' => 'nueva12345',
        ])->assertSessionHasErrors('correo');

        $this->post("/recuperar/{$token}", [
            'correo' => 'otra@example.com', 'password' => 'nueva12345', 'password_confirmation' => 'nueva12345',
        ])->assertSessionHasErrors('correo');

        $this->assertTrue(Auth::guard('cliente')->validate(['correo' => 'ana@example.com', 'password' => 'vieja12345']));
    }

    public function test_cuenta_solo_google_puede_crearse_una_contrasena(): void
    {
        $this->cliente(['password' => null, 'proveedor_oauth' => 'google', 'correo_verificado_en' => null]);
        $token = $this->pedirEnlace();

        $this->post("/recuperar/{$token}", [
            'correo' => 'ana@example.com', 'password' => 'nueva12345', 'password_confirmation' => 'nueva12345',
        ])->assertRedirect(route('portal.ingresar'));

        $this->post('/ingresar', ['correo' => 'ana@example.com', 'password' => 'nueva12345'])
            ->assertRedirect(route('compras.crear'));
    }

    public function test_ingresar_enlaza_a_recuperar(): void
    {
        $this->get('/ingresar')->assertSee(route('portal.recuperar'));
    }
}
