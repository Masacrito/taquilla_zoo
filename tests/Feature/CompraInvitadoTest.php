<?php

namespace Tests\Feature;

use App\Mail\CodigoVerificacion;
use App\Mail\ComprobanteCompra;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\Nacionalidad;
use App\Models\Pago;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Models\VerificacionCorreo;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Compra sin cuenta: un correo verificado con código basta para comprar.
 *
 * Lo delicado no es el camino feliz sino sus bordes: que el código del
 * invitado no abra cuentas, que el folio solo no abra compras, y que el
 * personal interno siga sin poder comprar.
 */
class CompraInvitadoTest extends TestCase
{
    use RefreshDatabase;

    private const CORREO = 'invitada@example.com';

    private Rubro $rubro;
    private string $fechaVisita;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(CatalogosSeeder::class);

        $this->rubro = Rubro::create([
            'tipo'               => 'Adulto',
            'id_nacionalidad'    => Nacionalidad::where('nombre', 'NACIONAL')->value('id'),
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'ADULTO NACIONAL')->value('id'),
            'id_tipo_acceso'     => TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->value('id'),
            'precio_centavos'    => 3500,
            'vigente_desde'      => now()->subMonth(),
            'activo'             => true,
        ]);

        $this->fechaVisita = Carbon::parse('next tuesday')->toDateString();
        AforoDiario::create(['fecha' => $this->fechaVisita]);
    }

    private function carrito(): array
    {
        return [
            'fecha_visita' => $this->fechaVisita,
            'renglones'    => [['id_rubro' => $this->rubro->id, 'cant_hombre' => 2, 'cant_mujer' => 1]],
        ];
    }

    /** Pide el código como invitado y devuelve el que salió en el correo. */
    private function pedirCodigo(string $correo = self::CORREO): string
    {
        Mail::fake();

        $this->post('/comprar/invitado', ['correo' => $correo])
            ->assertRedirect(route('invitado.verificar'));

        $codigo = null;

        Mail::assertQueued(CodigoVerificacion::class, function (CodigoVerificacion $mail) use (&$codigo, $correo) {
            $codigo = $mail->codigo;

            return $mail->hasTo($correo) && $mail->proposito === VerificacionCorreo::INVITADO;
        });

        return $codigo;
    }

    private function entrarComoInvitado(string $correo = self::CORREO): void
    {
        $codigo = $this->pedirCodigo($correo);

        $this->post('/comprar/invitado/verificar', ['codigo' => $codigo])
            ->assertRedirect(route('compras.crear'));
    }

    private function comprarComoInvitado(string $correo = self::CORREO): Compra
    {
        $this->entrarComoInvitado($correo);
        $this->post('/comprar', $this->carrito())->assertRedirect();

        return Compra::latest('id')->firstOrFail();
    }

    // ═══ La elección ═══

    public function test_sin_sesion_comprar_lleva_a_elegir_cuenta_o_invitado(): void
    {
        $this->get('/comprar')->assertRedirect(route('compras.acceso'));

        $this->get('/comprar/acceso')
            ->assertOk()
            ->assertSee('Con mi cuenta')
            ->assertSee('Como invitado')
            ->assertSee('Ingrese una dirección de correo electrónico para enviarle sus boletos');
    }

    // ═══ El recorrido ═══

    public function test_el_invitado_verifica_su_correo_compra_y_recibe_su_qr(): void
    {
        $this->entrarComoInvitado();

        $this->get('/comprar')->assertOk()->assertSee(self::CORREO);

        $this->post('/comprar', $this->carrito())->assertRedirect();

        $compra = Compra::firstOrFail();
        $this->assertNull($compra->id_cliente);
        $this->assertSame(self::CORREO, $compra->correo_invitado);
        $this->assertSame(10500, $compra->total_centavos);
        $this->assertSame(0, Cliente::count(), 'Comprar como invitado no crea cuenta.');

        // El banco confirma y lo devuelve a SU pantalla de retorno, firmada.
        $pago = Pago::where('id_compra', $compra->id)->firstOrFail();

        $retorno = $this->post(route('pago.simulado.confirmar', $pago->referencia_externa), ['resultado' => 'aprobar']);
        $retorno->assertRedirect();
        $this->assertStringContainsString('signature=', $retorno->headers->get('Location'));

        $compra->refresh();
        $this->assertSame(Compra::PAGADA, $compra->estado);

        // Los boletos van al correo verificado.
        Mail::assertQueued(ComprobanteCompra::class, fn ($mail) => $mail->hasTo(self::CORREO));

        $this->get($retorno->headers->get('Location'))->assertOk()->assertSee('Pago confirmado');
        $this->get($compra->urlDetalle())->assertOk()->assertSee($compra->folio)->assertDontSee('Mis compras');
        $this->get($compra->urlQr())->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    /** El comprobante de un invitado se arma sin cliente: correo, PDF y enlace. */
    public function test_el_comprobante_del_invitado_se_genera_sin_cuenta(): void
    {
        $compra = $this->comprarComoInvitado();
        $compra->update(['estado' => Compra::PAGADA, 'qr_token' => 'token-de-prueba']);

        $html = (new ComprobanteCompra($compra->fresh()))->render();

        $this->assertStringContainsString($compra->folio, $html);
        $this->assertStringContainsString('signature=', $html, 'El botón lleva al enlace firmado.');
        $this->assertStringNotContainsString('Hola', $html);
    }

    // ═══ El código ═══

    public function test_un_codigo_equivocado_no_deja_comprar(): void
    {
        $codigo = $this->pedirCodigo();
        $otro   = $codigo === '000000' ? '111111' : '000000';

        $this->post('/comprar/invitado/verificar', ['codigo' => $otro])
            ->assertSessionHasErrors('codigo');

        $this->get('/comprar')->assertRedirect(route('compras.acceso'));
        $this->post('/comprar', $this->carrito());
        $this->assertSame(0, Compra::count());
    }

    public function test_el_correo_verificado_caduca(): void
    {
        $this->entrarComoInvitado();

        Carbon::setTestNow(now()->addMinutes(config('taquilla.compra.minutos_sesion_invitado') + 1));

        $this->get('/comprar')->assertRedirect(route('compras.acceso'));
    }

    /**
     * El hueco que el propósito del código cierra: /registro/verificar inicia
     * sesión en la cuenta del correo con cualquier código válido. Uno pedido
     * para comprar como invitado no debe servir ahí.
     */
    public function test_el_codigo_de_invitado_no_abre_la_cuenta_de_ese_correo(): void
    {
        $this->cuenta(self::CORREO);
        $codigo = $this->pedirCodigo();

        $this->post('/registro/verificar', ['correo' => self::CORREO, 'codigo' => $codigo])
            ->assertSessionHasErrors('codigo');

        $this->assertGuest('cliente');
    }

    // ═══ Correo que ya tiene cuenta ═══

    public function test_si_el_correo_ya_tiene_cuenta_la_compra_se_liga_a_ella_sin_iniciar_sesion(): void
    {
        $cliente = $this->cuenta(self::CORREO);

        $compra = $this->comprarComoInvitado();

        $this->assertSame($cliente->id, $compra->id_cliente);
        $this->assertTrue($compra->esDeInvitado());
        $this->assertGuest('cliente');

        // Y al ingresar con su cuenta, ahí está.
        $this->actingAs($cliente, 'cliente')
            ->get('/mis-compras')
            ->assertOk()
            ->assertSee($compra->folio);
    }

    // ═══ El folio solo no abre nada ═══

    public function test_sin_firma_valida_no_se_ve_la_compra_de_un_invitado(): void
    {
        $compra = $this->comprarComoInvitado();

        $this->get("/compra/{$compra->folio}")->assertForbidden();
        $this->get("/compra/{$compra->folio}?signature=inventada")->assertForbidden();
        $this->get("/compra/{$compra->folio}/qr")->assertForbidden();

        // Tampoco por las rutas con sesión de otro cliente.
        $this->actingAs($this->cuenta('otra@example.com'), 'cliente')
            ->get("/mis-compras/{$compra->folio}")
            ->assertNotFound();
    }

    /** Un enlace firmado no convierte en pública una compra hecha con cuenta. */
    public function test_el_enlace_firmado_no_abre_compras_hechas_con_cuenta(): void
    {
        $cliente = $this->cuenta('ana@example.com');

        $this->actingAs($cliente, 'cliente')->post('/comprar', $this->carrito());
        $compra = Compra::firstOrFail();
        $this->assertFalse($compra->esDeInvitado());

        $this->get(URL::signedRoute('invitado.ver', ['folio' => $compra->folio]))->assertNotFound();
    }

    // ═══ Aislamiento de guards (brief §10.6) ═══

    public function test_una_cuenta_interna_tampoco_compra_como_invitada(): void
    {
        $this->actingAs(Cuenta::superAdmin(), 'web');

        $this->get('/comprar/acceso')->assertForbidden();
        $this->post('/comprar/invitado', ['correo' => self::CORREO])->assertForbidden();
        $this->post('/comprar', $this->carrito())->assertForbidden();

        $this->assertSame(0, Compra::count());
    }

    // ═══ El panel ═══

    /** Las pantallas internas daban por hecho que toda compra tiene cliente. */
    public function test_el_panel_muestra_y_encuentra_las_compras_de_invitado(): void
    {
        $compra = $this->comprarComoInvitado();
        $compra->update(['estado' => Compra::UTILIZADA]);

        $admin = Cuenta::superAdmin();

        $this->actingAs($admin, 'web')
            ->get('/admin/compras?q=invitada@')
            ->assertOk()
            ->assertSee($compra->folio)
            ->assertSee(self::CORREO)
            ->assertSee('invitado');

        $this->actingAs($admin, 'web')
            ->get("/admin/compras/{$compra->id}")
            ->assertOk()
            ->assertSee('compró como invitado');

        // En estadísticas cuenta como un comprador, aunque no tenga cuenta.
        $resumen = app(\App\Services\Reporte\EstadisticaService::class)
            ->generar($this->fechaVisita, $this->fechaVisita)['resumen'];

        $this->assertSame(1, $resumen['clientes']);
        $this->assertSame(3, $resumen['pases']);
    }

    private function cuenta(string $correo): Cliente
    {
        return Cliente::create([
            'correo'               => $correo,
            'password'             => Hash::make('secreto12345'),
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
        ]);
    }
}
