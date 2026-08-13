<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioSocialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);

        // Credenciales ficticias: sin esto el controlador responde 404.
        config([
            'services.google.client_id'     => 'id-de-prueba',
            'services.google.client_secret' => 'secreto-de-prueba',
            'services.google.redirect'      => 'http://localhost/auth/google/callback',
        ]);
    }

    private function fingirUsuarioGoogle(string $correo, string $id = 'google-123', string $nombre = 'Ana Pérez'): void
    {
        $usuario = (new UsuarioSocialite())->map([
            'id'    => $id,
            'name'  => $nombre,
            'email' => $correo,
        ]);

        $proveedor = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $proveedor->shouldReceive('user')->andReturn($usuario);

        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);
    }

    private function cliente(string $correo, bool $verificado, ?string $oauthId = null): Cliente
    {
        return Cliente::create([
            'correo'               => $correo,
            'password'             => $oauthId ? null : Hash::make('secreto12345'),
            'proveedor_oauth'      => $oauthId ? 'google' : null,
            'proveedor_oauth_id'   => $oauthId,
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => $verificado ? now() : null,
        ]);
    }

    // ═══ Bandera de configuración ═══

    public function test_sin_credenciales_la_ruta_no_existe(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_sin_credenciales_no_se_muestra_el_boton(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/ingresar')->assertOk()->assertDontSee('Continuar con Google');
    }

    public function test_con_credenciales_aparece_el_boton(): void
    {
        $this->get('/ingresar')->assertOk()->assertSee('Continuar con Google');
    }

    // ═══ Enlace con cuenta existente ═══

    public function test_enlaza_una_cuenta_local_ya_verificada(): void
    {
        $cliente = $this->cliente('ana@example.com', verificado: true);
        $this->fingirUsuarioGoogle('ana@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('compras.crear'));

        $cliente->refresh();
        $this->assertSame('google', $cliente->proveedor_oauth);
        $this->assertSame('google-123', $cliente->proveedor_oauth_id);
        $this->assertAuthenticated('cliente');
    }

    /**
     * Regla del brief §9: enlazar con OAuth SOLO si el correo ya está
     * verificado. Si no, primero hay que probar la posesión con el código.
     */
    public function test_no_enlaza_una_cuenta_local_sin_verificar(): void
    {
        $cliente = $this->cliente('ana@example.com', verificado: false);
        $this->fingirUsuarioGoogle('ana@example.com');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('portal.verificar', ['correo' => 'ana@example.com']));

        $cliente->refresh();
        $this->assertNull($cliente->proveedor_oauth);
        $this->assertGuest('cliente');
    }

    public function test_una_cuenta_ya_enlazada_entra_directo(): void
    {
        $this->cliente('ana@example.com', verificado: true, oauthId: 'google-123');
        $this->fingirUsuarioGoogle('ana@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('compras.crear'));
        $this->assertAuthenticated('cliente');
    }

    // ═══ Alta nueva ═══

    public function test_un_correo_nuevo_pide_completar_el_perfil_sin_crear_el_cliente(): void
    {
        $this->fingirUsuarioGoogle('nueva@example.com', 'google-999', 'Nueva Visitante');

        $this->get('/auth/google/callback')->assertRedirect(route('portal.completar'));

        // Google no da fecha de nacimiento, género ni teléfono: no se inventa
        // nada ni se crea el registro a medias.
        $this->assertSame(0, Cliente::where('correo', 'nueva@example.com')->count());
        $this->assertGuest('cliente');
    }

    public function test_completar_el_perfil_crea_el_cliente_ya_verificado(): void
    {
        $this->fingirUsuarioGoogle('nueva@example.com', 'google-999', 'Nueva Visitante');
        $this->get('/auth/google/callback');

        $this->post('/registro/completar', [
            'nombre'           => 'Nueva',
            'apellidos'        => 'Visitante',
            'fecha_nacimiento' => '1995-06-15',
            'genero'           => 'Mujer',
            'telefono'         => '9610002222',
        ])->assertRedirect(route('compras.crear'));

        $cliente = Cliente::where('correo', 'nueva@example.com')->firstOrFail();

        $this->assertSame('google', $cliente->proveedor_oauth);
        $this->assertNull($cliente->password);
        $this->assertTrue($cliente->soloOauth());
        // Google ya comprobó la posesión del correo: no hace falta código.
        $this->assertNotNull($cliente->correo_verificado_en);
        $this->assertAuthenticated('cliente');
    }

    public function test_completar_sin_sesion_pendiente_manda_al_registro(): void
    {
        $this->get('/registro/completar')->assertRedirect(route('portal.registro'));
    }

    // ═══ Ingreso por contraseña de una cuenta OAuth ═══

    public function test_una_cuenta_solo_oauth_no_entra_por_contrasena(): void
    {
        $this->cliente('ana@example.com', verificado: true, oauthId: 'google-123');

        $this->post('/ingresar', ['correo' => 'ana@example.com', 'password' => 'loquesea'])
            ->assertSessionHasErrors('correo');

        $this->assertGuest('cliente');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
