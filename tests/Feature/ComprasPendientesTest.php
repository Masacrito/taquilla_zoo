<?php

namespace Tests\Feature;

use App\Jobs\ExpirarComprasPendientes;
use App\Mail\ComprobanteCompra;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Models\Nacionalidad;
use App\Models\Pago;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\NotificacionPago;
use App\Services\Pago\PasarelaSimulada;
use App\Services\Venta\CotizarCompraService;
use App\Services\Venta\RegistrarCompraService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\PermisosTaquillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lo que pasa con una compra que se quedó sin pagar (2026-10-11): se retoma
 * en vez de duplicarse, se puede pagar después y se puede archivar. Y, de
 * paso, la edición de rubros que faltaba en el panel.
 */
class ComprasPendientesTest extends TestCase
{
    use RefreshDatabase;

    private Rubro $rubro;
    private string $fechaVisita;
    private string $otraFecha;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(CatalogosSeeder::class);
        $this->seed(PermisosTaquillaSeeder::class);

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
        $this->otraFecha   = Carbon::parse('next tuesday')->addDay()->toDateString();
        AforoDiario::create(['fecha' => $this->fechaVisita]);
        AforoDiario::create(['fecha' => $this->otraFecha]);
    }

    private function cliente(string $correo = 'ana@example.com'): Cliente
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

    private function carrito(int $hombres, ?string $fecha = null): array
    {
        return [
            'fecha_visita' => $fecha ?? $this->fechaVisita,
            'renglones'    => [[
                'id_rubro'    => $this->rubro->id,
                'cant_hombre' => $hombres,
                'cant_mujer'  => 0,
                ...$this->procedencia(),
            ]],
        ];
    }

    private function aprobar(Pago $pago): bool
    {
        return app(ConfirmarPagoService::class)->confirmar(new NotificacionPago(
            firmaValida:       true,
            referenciaExterna: $pago->referencia_externa,
            estado:            'aprobado',
            montoCentavos:     $pago->monto_centavos,
            autorizacion:      'SIM-TEST',
            payload:           [],
        ), PasarelaSimulada::PROVEEDOR);
    }

    // ═══ Un solo pendiente por comprador ═══

    public function test_regresar_a_modificar_el_carrito_no_duplica_la_compra(): void
    {
        $this->actingAs($this->cliente(), 'cliente');

        $this->post('/comprar', $this->carrito(2))->assertRedirect();
        $folio = Compra::firstOrFail()->folio;

        // Regresa, cambia cantidades y día, y vuelve a darle a pagar.
        $this->post('/comprar', $this->carrito(3, $this->otraFecha))->assertRedirect();

        $this->assertSame(1, Compra::count(), 'Debe seguir siendo una sola compra.');

        $compra = Compra::with('detalle')->firstOrFail();
        $this->assertSame($folio, $compra->folio, 'El folio se conserva: no se consume otro.');
        $this->assertSame(12000, $compra->total_centavos);
        $this->assertSame(3, $compra->pases_total);
        $this->assertSame($this->otraFecha, $compra->fecha_visita->toDateString());
        $this->assertCount(1, $compra->detalle, 'El detalle se reemplaza, no se acumula.');

        // El carrito anterior no se pierde: queda en la bitácora.
        $this->assertTrue(
            Movimiento::where('tabla', 'compras')->where('registro_id', $compra->id)
                ->where('detalles->anterior->total_centavos', 8000)->exists()
        );
    }

    public function test_el_cobro_de_un_carrito_anterior_no_paga_la_compra_modificada(): void
    {
        $this->actingAs($this->cliente(), 'cliente');

        $this->post('/comprar', $this->carrito(2));
        $cobroViejo = Pago::firstOrFail();

        $this->post('/comprar', $this->carrito(3));

        // Llega el webhook del primer cobro, por $80, cuando la compra ya vale $120.
        $this->assertFalse($this->aprobar($cobroViejo));
        $this->assertSame(Compra::PENDIENTE_PAGO, Compra::firstOrFail()->estado);
    }

    public function test_despues_de_pagar_la_siguiente_compra_es_una_nueva(): void
    {
        $this->actingAs($this->cliente(), 'cliente');

        $this->post('/comprar', $this->carrito(1));
        $this->aprobar(Pago::firstOrFail());

        $this->post('/comprar', $this->carrito(1));

        $this->assertSame(2, Compra::count());
        $this->assertSame(1, Compra::where('estado', Compra::PAGADA)->count());
    }

    public function test_el_pendiente_de_un_comprador_no_se_mezcla_con_el_de_otro(): void
    {
        $this->actingAs($this->cliente('ana@example.com'), 'cliente')->post('/comprar', $this->carrito(1));
        $this->actingAs($this->cliente('luis@example.com'), 'cliente')->post('/comprar', $this->carrito(4));

        $this->assertSame(2, Compra::count());
        $this->assertEqualsCanonicalizing([4000, 16000], Compra::pluck('total_centavos')->all());
    }

    public function test_el_invitado_tambien_retoma_su_pendiente_por_correo(): void
    {
        $cotizar   = fn (int $n) => app(CotizarCompraService::class)->cotizar(
            [['id_rubro' => $this->rubro->id, 'cant_hombre' => $n, 'cant_mujer' => 0]], $this->fechaVisita,
        );
        $registrar = app(RegistrarCompraService::class);

        $primera = $registrar->registrarInvitado('Invitada@Example.com', $cotizar(1), $this->fechaVisita);
        $segunda = $registrar->registrarInvitado('invitada@example.com', $cotizar(2), $this->fechaVisita);
        $ajena   = $registrar->registrarInvitado('otra@example.com', $cotizar(1), $this->fechaVisita);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertNotSame($primera->id, $ajena->id);
        $this->assertSame(8000, $segunda->total_centavos);
    }

    // ═══ Botón Pagar ═══

    public function test_pagar_una_pendiente_genera_un_cobro_nuevo_y_reinicia_el_reloj(): void
    {
        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(2));

        $compra = Compra::firstOrFail();
        Compra::whereKey($compra->id)->update(['fecha_compra' => now()->subMinutes(14)]);

        $this->get('/mis-compras')->assertOk()->assertSee($compra->urlPagar(), false);

        $this->post($compra->urlPagar())->assertRedirect();

        $this->assertSame(2, Pago::where('id_compra', $compra->id)->count());
        $this->assertTrue($compra->refresh()->fecha_compra->gt(now()->subMinute()));

        // Con el reloj reiniciado, el job de expiración ya no la toca.
        (new ExpirarComprasPendientes())->handle(app(\App\Services\Auditoria\BitacoraService::class));
        $this->assertSame(Compra::PENDIENTE_PAGO, $compra->refresh()->estado);
    }

    public function test_un_segundo_cobro_de_la_misma_compra_no_la_paga_otra_vez(): void
    {
        Mail::fake();
        Log::spy();

        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(2));

        $compra = Compra::firstOrFail();
        $this->post($compra->urlPagar());   // segundo cobro abierto, p. ej. en otra pestaña

        [$primero, $segundo] = Pago::orderBy('id')->get()->all();

        $this->aprobar($primero);
        $qr = $compra->refresh()->qr_token;

        // El banco cobra también el segundo. Se acepta (200) para que no lo
        // reenvíe, pero no vuelve a «pagar» la compra.
        $this->assertTrue($this->aprobar($segundo));

        Mail::assertQueued(ComprobanteCompra::class, 1);
        $this->assertSame($qr, $compra->refresh()->qr_token);

        // Queda registrado como cobrado y avisado, para poder devolverlo.
        $this->assertSame(Pago::APROBADO, $segundo->refresh()->estado);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_pagar_tiene_limite_de_intentos(): void
    {
        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(1));

        $url = Compra::firstOrFail()->urlPagar();

        foreach (range(1, 10) as $intento) {
            $this->post($url)->assertRedirect();
        }

        $this->post($url)->assertStatus(429);
        $this->assertSame(11, Pago::count(), 'El cobro del alta más diez reintentos; el undécimo no entra.');
    }

    public function test_no_se_puede_pagar_una_compra_que_ya_no_esta_pendiente(): void
    {
        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(1));

        $compra = Compra::firstOrFail();
        $compra->update(['estado' => Compra::EXPIRADA]);

        $this->post($compra->urlPagar())->assertSessionHas('error');
        $this->assertSame(1, Pago::count(), 'No se abre un cobro para una compra expirada.');
    }

    public function test_pagar_no_salta_un_dia_que_se_cerro_despues(): void
    {
        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(1));

        AforoDiario::where('fecha', $this->fechaVisita)->update(['cerrado' => true]);

        $this->post(Compra::firstOrFail()->urlPagar())->assertSessionHas('error');
        $this->assertSame(1, Pago::count());
    }

    public function test_nadie_paga_ni_archiva_la_compra_de_otro(): void
    {
        $this->actingAs($this->cliente('ana@example.com'), 'cliente')->post('/comprar', $this->carrito(1));
        $compra = Compra::firstOrFail();

        $this->actingAs($this->cliente('luis@example.com'), 'cliente');

        $this->post(route('compras.pagar', $compra->folio))->assertNotFound();
        $this->post(route('compras.archivar', $compra->folio))->assertNotFound();
        $this->assertNull($compra->refresh()->archivada_en);
    }

    public function test_el_invitado_paga_su_pendiente_solo_con_el_enlace_firmado(): void
    {
        $cotizacion = app(CotizarCompraService::class)->cotizar(
            [['id_rubro' => $this->rubro->id, 'cant_hombre' => 1, 'cant_mujer' => 0]], $this->fechaVisita,
        );
        $compra = app(RegistrarCompraService::class)
            ->registrarInvitado('invitada@example.com', $cotizacion, $this->fechaVisita);

        $this->post("/compra/{$compra->folio}/pagar")->assertForbidden();
        $this->assertSame(0, Pago::count());

        $this->post($compra->urlPagar())->assertRedirect();
        $this->assertSame(1, Pago::count());
    }

    // ═══ Archivar ═══

    public function test_archivar_saca_la_compra_de_la_bandeja_sin_cambiarle_el_estado(): void
    {
        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(1));
        $compra = Compra::firstOrFail();

        $this->post(route('compras.archivar', $compra->folio))->assertSessionHas('success');

        $this->assertSame(Compra::PENDIENTE_PAGO, $compra->refresh()->estado);
        // Se busca su enlace y no el folio: el aviso de «se archivó» lo nombra.
        $enlace = route('compras.ver', $compra->folio);

        $this->get('/mis-compras')->assertOk()->assertDontSee($enlace, false);
        $this->get('/mis-compras?archivadas=1')->assertOk()->assertSee($enlace, false);

        $this->post(route('compras.desarchivar', $compra->folio));
        $this->get('/mis-compras')->assertSee($enlace, false);
    }

    public function test_una_compra_con_qr_vigente_no_se_archiva(): void
    {
        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(1));
        $this->aprobar(Pago::firstOrFail());
        $compra = Compra::firstOrFail();

        $this->post(route('compras.archivar', $compra->folio))->assertStatus(422);

        $this->assertNull($compra->refresh()->archivada_en);
        $this->get('/mis-compras')->assertSee($compra->folio)->assertDontSee('Archivar');
    }

    public function test_una_pendiente_archivada_vuelve_a_la_bandeja_al_pagarse(): void
    {
        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(1));
        $compra = Compra::firstOrFail();

        $this->post(route('compras.archivar', $compra->folio));
        $this->aprobar(Pago::firstOrFail());

        $this->assertSame(Compra::PAGADA, $compra->refresh()->estado);
        $this->assertNull($compra->archivada_en);
    }

    public function test_las_expiradas_se_archivan_solas(): void
    {
        $this->actingAs($this->cliente(), 'cliente');
        $this->post('/comprar', $this->carrito(1));

        $compra = Compra::firstOrFail();
        Compra::whereKey($compra->id)->update(['fecha_compra' => now()->subMinutes(Compra::MINUTOS_PARA_EXPIRAR + 1)]);

        (new ExpirarComprasPendientes())->handle(app(\App\Services\Auditoria\BitacoraService::class));

        $compra->refresh();
        $this->assertSame(Compra::EXPIRADA, $compra->estado);
        $this->assertNotNull($compra->archivada_en);
        $this->get('/mis-compras')->assertDontSee($compra->folio);
    }

    // ═══ Edición de rubros ═══

    public function test_el_panel_ofrece_editar_cada_rubro_y_guarda_los_cambios(): void
    {
        $admin = Cuenta::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin, 'web')
            ->get('/admin/rubros')
            ->assertOk()
            ->assertSee('data-editar="' . $this->rubro->id . '"', false)
            ->assertSee(route('admin.rubros.update', $this->rubro), false)
            // El precio llega precargado en pesos, no en centavos.
            ->assertSee('value="40.00"', false);

        $this->actingAs($admin, 'web')
            ->put(route('admin.rubros.update', $this->rubro), [
                '_rubro'             => $this->rubro->id,
                'tipo'               => 'Acceso general',
                'id_nacionalidad'    => $this->rubro->id_nacionalidad,
                'id_subnacionalidad' => $this->rubro->id_subnacionalidad,
                'id_tipo_acceso'     => $this->rubro->id_tipo_acceso,
                'precio'             => '45.50',
                'vigente_desde'      => now()->subMonth()->toDateString(),
                'activo'             => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->rubro->refresh();
        $this->assertSame('Acceso general', $this->rubro->tipo);
        $this->assertSame(4550, $this->rubro->precio_centavos);
    }

    public function test_un_error_al_editar_reabre_ese_rubro_y_no_el_formulario_de_alta(): void
    {
        $admin = Cuenta::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin, 'web')
            ->from('/admin/rubros')
            ->put(route('admin.rubros.update', $this->rubro), ['_rubro' => $this->rubro->id, 'tipo' => ''])
            ->assertSessionHasErrors('tipo');

        $html = $this->actingAs($admin, 'web')->get('/admin/rubros')->getContent();

        $this->assertMatchesRegularExpression('/data-edicion="' . $this->rubro->id . '"\s*>/', $html, 'La fila de edición debe venir abierta.');
        $this->assertStringNotContainsString('<details class="card mb-6" open', $html);
    }
}
