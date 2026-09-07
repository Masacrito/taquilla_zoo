<?php

namespace Tests\Feature;

use App\Exceptions\DiaNoDisponibleException;
use App\Jobs\ExpirarComprasPendientes;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Nacionalidad;
use App\Models\Pago;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Services\Acceso\QrTokenService;
use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\NotificacionPago;
use App\Services\Venta\CotizarCompraService;
use App\Services\Venta\RegistrarCompraService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Las pruebas que el brief §10 declara innegociables.
 */
class NucleoCompraTest extends TestCase
{
    use RefreshDatabase;

    private Rubro $rubro;
    private string $fechaVisita;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(CatalogosSeeder::class);

        $this->rubro = Rubro::create([
            'tipo'               => 'Adulto nacional',
            'id_nacionalidad'    => Nacionalidad::where('nombre', 'NACIONAL')->value('id'),
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'ADULTO NACIONAL')->value('id'),
            'id_tipo_acceso'     => TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->value('id'),
            'precio_centavos'    => 4000,           // $40.00
            'vigente_desde'      => now()->subMonth(),
            'activo'             => true,
        ]);

        // Un martes: el zoológico abre.
        $this->fechaVisita = Carbon::parse('next tuesday')->toDateString();
        AforoDiario::create([
            'fecha'       => $this->fechaVisita,
        ]);
    }

    private function cliente(string $correo = 'visitante@example.com'): Cliente
    {
        return Cliente::create([
            'correo'               => $correo,
            'nombre'               => 'Visitante',
            'apellidos'            => 'De Prueba',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'No especificado',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
        ]);
    }

    private function cotizar(array $renglones = null): \App\Services\Venta\Cotizacion
    {
        return app(CotizarCompraService::class)->cotizar(
            $renglones ?? [['id_rubro' => $this->rubro->id, 'cant_hombre' => 1, 'cant_mujer' => 1]],
            $this->fechaVisita,
        );
    }

    private function comprar(int $adultos = 2, ?Cliente $cliente = null): Compra
    {
        $cotizacion = $this->cotizar([
            ['id_rubro' => $this->rubro->id, 'cant_hombre' => $adultos, 'cant_mujer' => 0],
        ]);

        return app(RegistrarCompraService::class)->registrar(
            $cliente ?? $this->cliente(),
            $cotizacion,
            $this->fechaVisita,
        );
    }

    // ═══ §10.1 — El total ignora lo que mande el navegador ═══

    public function test_la_cotizacion_ignora_cualquier_precio_del_request(): void
    {
        $cotizacion = $this->cotizar([[
            'id_rubro'    => $this->rubro->id,
            'cant_hombre' => 2,
            'cant_mujer'  => 0,
            // Basura inyectada por un cliente malicioso:
            'precio'           => 1,
            'precio_centavos'  => 1,
            'importe_centavos' => 1,
            'total'            => 1,
        ]]);

        // 2 × $40.00 = $80.00, no lo que pidió el navegador.
        $this->assertSame(8000, $cotizacion->totalCentavos);
        $this->assertSame(2, $cotizacion->pasesTotal);
    }

    public function test_no_se_puede_cotizar_un_rubro_fuera_de_vigencia(): void
    {
        $this->rubro->update(['vigente_hasta' => now()->subDay()]);

        $this->expectException(ValidationException::class);
        $this->cotizar();
    }

    // ═══ La venta de un día abierto no tiene tope ═══
    //
    // Sustituye a la vieja prueba de concurrencia de aforo: el área operativa
    // definió que no hay cupo máximo ni mínimo, así que lo que hay que
    // garantizar es lo contrario de antes — que nada tope la venta.

    public function test_la_venta_de_un_dia_abierto_no_tiene_tope(): void
    {
        $vendidas = 0;

        // 50 compras de 40 pases cada una: 2000 personas para el mismo día.
        for ($i = 0; $i < 50; $i++) {
            $this->comprar(40, $this->cliente("comprador{$i}@example.com"));
            $vendidas++;
        }

        $this->assertSame(50, $vendidas, 'Ninguna compra debió rechazarse: no hay cupo.');
        $this->assertSame(2000, (int) Compra::sum('pases_total'));
    }

    public function test_no_se_puede_comprar_para_un_dia_cerrado(): void
    {
        AforoDiario::where('fecha', $this->fechaVisita)->update(['cerrado' => true]);

        $this->expectException(DiaNoDisponibleException::class);
        $this->comprar();
    }

    public function test_no_se_puede_comprar_para_un_dia_fuera_del_calendario(): void
    {
        AforoDiario::where('fecha', $this->fechaVisita)->delete();

        $this->expectException(DiaNoDisponibleException::class);
        $this->comprar();
    }

    public function test_los_folios_son_consecutivos_y_sin_huecos(): void
    {
        $folios = [];
        for ($i = 0; $i < 5; $i++) {
            $folios[] = $this->comprar(1, $this->cliente("f{$i}@example.com"))->folio;
        }

        $anio = now()->format('Y');
        $this->assertSame([
            "ZM-{$anio}-000001", "ZM-{$anio}-000002", "ZM-{$anio}-000003",
            "ZM-{$anio}-000004", "ZM-{$anio}-000005",
        ], $folios);
    }

    // ═══ §10.5 — Snapshot de precio ═══

    public function test_cambiar_el_precio_de_un_rubro_no_altera_compras_anteriores(): void
    {
        $compra = $this->comprar(2);
        $this->assertSame(8000, $compra->total_centavos);

        // El zoológico sube la tarifa a $60.
        $this->rubro->update(['precio_centavos' => 6000]);

        $compra->refresh()->load('detalle');
        $detalle = $compra->detalle->first();

        $this->assertSame(8000, $compra->total_centavos);
        $this->assertSame(4000, $detalle->precio_centavos_snap);
        $this->assertSame(8000, $detalle->importe_centavos);
        $this->assertSame('Adulto nacional', $detalle->rubro_nombre_snap);

        // Y una compra nueva sí toma el precio nuevo.
        $nueva = $this->comprar(1, $this->cliente('otro@example.com'));
        $this->assertSame(6000, $nueva->total_centavos);
    }

    // ═══ §10.4 — Idempotencia del webhook ═══

    private function notificacion(Compra $compra, ?int $monto = null): NotificacionPago
    {
        return new NotificacionPago(
            firmaValida:       true,
            referenciaExterna: 'SIM-REENVIADA-001',
            estado:            'aprobado',
            montoCentavos:     $monto ?? $compra->total_centavos,
            autorizacion:      'AUTH123',
            payload:           ['folio' => $compra->folio],
        );
    }

    public function test_el_mismo_webhook_tres_veces_produce_una_sola_compra_pagada(): void
    {
        $compra   = $this->comprar(2);
        $servicio = app(ConfirmarPagoService::class);

        foreach (range(1, 3) as $intento) {
            $this->assertTrue($servicio->confirmar($this->notificacion($compra), 'simulada'));
        }

        $compra->refresh();

        $this->assertSame(Compra::PAGADA, $compra->estado);
        $this->assertSame(1, Pago::where('referencia_externa', 'SIM-REENVIADA-001')->count());
        $this->assertSame(1, Pago::where('id_compra', $compra->id)->where('estado', Pago::APROBADO)->count());
        $this->assertNotNull($compra->qr_token);
    }

    public function test_un_webhook_con_firma_invalida_se_rechaza(): void
    {
        $compra = $this->comprar(2);

        $notificacion = new NotificacionPago(
            firmaValida: false,
            referenciaExterna: 'SIM-FALSA',
            estado: 'aprobado',
            montoCentavos: $compra->total_centavos,
            autorizacion: null,
            payload: ['folio' => $compra->folio],
        );

        $this->assertFalse(app(ConfirmarPagoService::class)->confirmar($notificacion, 'simulada'));
        $this->assertSame(Compra::PENDIENTE_PAGO, $compra->refresh()->estado);
        $this->assertNull($compra->qr_token);
    }

    public function test_un_webhook_con_monto_distinto_no_marca_la_compra_pagada(): void
    {
        $compra = $this->comprar(2);   // $80.00

        // El banco reporta $1.00.
        $this->assertFalse(
            app(ConfirmarPagoService::class)->confirmar($this->notificacion($compra, 100), 'simulada')
        );

        $compra->refresh();
        $this->assertSame(Compra::PENDIENTE_PAGO, $compra->estado);
        $this->assertNull($compra->qr_token);
        $this->assertSame(Pago::RECHAZADO, Pago::where('id_compra', $compra->id)->first()->estado);
    }

    // ═══ QR ═══

    public function test_el_token_del_qr_se_verifica_sin_tocar_la_base(): void
    {
        $compra = $this->comprar(2);
        app(ConfirmarPagoService::class)->confirmar($this->notificacion($compra), 'simulada');

        $token = $compra->refresh()->qr_token;
        $datos = app(QrTokenService::class)->verificar($token);

        $this->assertNotNull($datos);
        $this->assertSame($compra->id, $datos['compra_id']);
        $this->assertSame($compra->folio, $datos['folio']);
    }

    public function test_un_token_alterado_se_rechaza(): void
    {
        $compra = $this->comprar(2);
        app(ConfirmarPagoService::class)->confirmar($this->notificacion($compra), 'simulada');

        $token = $compra->refresh()->qr_token;
        $qr    = app(QrTokenService::class);

        // Cambiar el id de compra invalida la firma.
        $partes = explode('.', $token);
        $partes[0] = (string) ($compra->id + 1);

        $this->assertNull($qr->verificar(implode('.', $partes)));
        $this->assertNull($qr->verificar($token . 'x'));
        $this->assertNull($qr->verificar('basura'));
    }

    // ═══ §10.3 — Concurrencia de acceso ═══

    public function test_dos_escaneos_simultaneos_consumen_los_pases_una_sola_vez(): void
    {
        $compra = $this->comprar(2);
        app(ConfirmarPagoService::class)->confirmar($this->notificacion($compra), 'simulada');

        $this->assertTrue(Compra::consumirPases($compra->id, 2));
        $this->assertFalse(Compra::consumirPases($compra->id, 2));   // ya no quedan
        $this->assertFalse(Compra::consumirPases($compra->id, 1));

        $this->assertSame(2, $compra->refresh()->pases_usados);
    }

    public function test_una_compra_sin_pagar_no_permite_el_acceso(): void
    {
        $compra = $this->comprar(2);

        $this->assertFalse(Compra::consumirPases($compra->id, 1));
    }

    // ═══ Expiración ═══

    public function test_la_compra_sin_pagar_expira_a_los_quince_minutos(): void
    {
        $compra = $this->comprar(3);

        // Han pasado 16 minutos sin pagar.
        $compra->update(['fecha_compra' => now()->subMinutes(16)]);

        (new ExpirarComprasPendientes())->handle(app(\App\Services\Auditoria\BitacoraService::class));

        $this->assertSame(Compra::EXPIRADA, $compra->refresh()->estado);
    }

    public function test_la_expiracion_no_toca_una_compra_ya_pagada(): void
    {
        $compra = $this->comprar(3);
        app(ConfirmarPagoService::class)->confirmar($this->notificacion($compra), 'simulada');

        $compra->update(['fecha_compra' => now()->subMinutes(30)]);

        (new ExpirarComprasPendientes())->handle(app(\App\Services\Auditoria\BitacoraService::class));

        $this->assertSame(Compra::PAGADA, $compra->refresh()->estado);
    }

    public function test_la_expiracion_no_toca_compras_recientes(): void
    {
        $compra = $this->comprar(3);

        (new ExpirarComprasPendientes())->handle(app(\App\Services\Auditoria\BitacoraService::class));

        $this->assertSame(Compra::PENDIENTE_PAGO, $compra->refresh()->estado);
    }
}
