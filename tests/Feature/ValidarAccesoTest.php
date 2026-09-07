<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\Nacionalidad;
use App\Models\Pago;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Services\Acceso\ResultadoAcceso;
use App\Services\Acceso\ValidarAccesoService;
use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\NotificacionPago;
use App\Services\Venta\CotizarCompraService;
use App\Services\Venta\RegistrarCompraService;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Brief §10.3 — Concurrencia de acceso, y el resto de las reglas del
 * torniquete.
 */
class ValidarAccesoTest extends TestCase
{
    use RefreshDatabase;

    private const TORNIQUETE = 'T-01';

    private Rubro $rubro;
    private string $fechaVisita;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->seed(AuthSeeder::class);
        $this->seed(CatalogosSeeder::class);

        $this->rubro = Rubro::create([
            'tipo'               => 'Adulto nacional',
            'id_nacionalidad'    => Nacionalidad::where('nombre', 'NACIONAL')->value('id'),
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'ADULTO NACIONAL')->value('id'),
            'id_tipo_acceso'     => TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->value('id'),
            'precio_centavos'    => 4000,
            'vigente_desde'      => now()->subMonth(),
            'activo'             => true,
        ]);

        // La visita es HOY: es el único día en que el código sirve.
        $this->fechaVisita = Carbon::today()->toDateString();
        AforoDiario::create(['fecha' => $this->fechaVisita, 'cupo_maximo' => 100]);
    }

    private function compraPagada(int $pases = 4): Compra
    {
        $cliente = Cliente::create([
            'correo'               => 'ana' . uniqid() . '@example.com',
            'password'             => Hash::make('secreto12345'),
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
        ]);

        $cotizacion = app(CotizarCompraService::class)->cotizar(
            [['id_rubro' => $this->rubro->id, 'cant_hombre' => $pases, 'cant_mujer' => 0]],
            $this->fechaVisita,
        );

        $compra = app(RegistrarCompraService::class)->registrar($cliente, $cotizacion, $this->fechaVisita);

        app(ConfirmarPagoService::class)->confirmar(new NotificacionPago(
            firmaValida: true,
            referenciaExterna: 'SIM-' . uniqid(),
            estado: 'aprobado',
            montoCentavos: $compra->total_centavos,
            autorizacion: 'AUTH',
            payload: ['folio' => $compra->folio],
        ), 'simulada');

        return $compra->refresh();
    }

    private function validar(string $token, int $pases = 1): ResultadoAcceso
    {
        return app(ValidarAccesoService::class)->validar($token, $pases, self::TORNIQUETE);
    }

    // ═══ Camino feliz ═══

    public function test_un_qr_valido_permite_el_acceso_y_descuenta_pases(): void
    {
        $compra = $this->compraPagada(4);

        $resultado = $this->validar($compra->qr_token, 2);

        $this->assertTrue($resultado->permitido);
        $this->assertSame(2, $compra->refresh()->pases_usados);
        $this->assertSame(Compra::ACCESO_PARCIAL, $compra->estado);
    }

    public function test_al_consumir_el_ultimo_pase_la_compra_queda_utilizada(): void
    {
        $compra = $this->compraPagada(2);

        $this->validar($compra->qr_token, 2);

        $this->assertSame(Compra::UTILIZADA, $compra->refresh()->estado);
        $this->assertSame(0, $compra->pasesDisponibles());
    }

    // ═══ §10.3 — Concurrencia ═══

    public function test_dos_escaneos_del_mismo_qr_no_duplican_el_acceso(): void
    {
        $compra = $this->compraPagada(2);

        $primero  = $this->validar($compra->qr_token, 2);
        $segundo  = $this->validar($compra->qr_token, 2);

        $this->assertTrue($primero->permitido);
        $this->assertFalse($segundo->permitido);
        $this->assertSame(ResultadoAcceso::SIN_PASES, $segundo->motivo);

        // Nunca más pases de los comprados.
        $this->assertSame(2, $compra->refresh()->pases_usados);
    }

    public function test_no_se_pueden_consumir_mas_pases_de_los_comprados(): void
    {
        $compra = $this->compraPagada(2);

        $resultado = $this->validar($compra->qr_token, 5);

        $this->assertFalse($resultado->permitido);
        $this->assertSame(0, $compra->refresh()->pases_usados);
    }

    // ═══ Rechazos ═══

    public function test_un_token_alterado_se_rechaza_sin_tocar_la_compra(): void
    {
        $compra = $this->compraPagada(2);

        $resultado = $this->validar($compra->qr_token . 'x');

        $this->assertFalse($resultado->permitido);
        $this->assertSame(ResultadoAcceso::TOKEN_INVALIDO, $resultado->motivo);
        $this->assertSame(0, $compra->refresh()->pases_usados);
    }

    public function test_un_qr_de_otra_fecha_se_rechaza(): void
    {
        $otraFecha = Carbon::today()->addDays(3)->toDateString();
        AforoDiario::create(['fecha' => $otraFecha, 'cupo_maximo' => 50]);

        $cliente = Cliente::create([
            'correo' => 'otro@example.com', 'password' => Hash::make('secreto12345'),
            'nombre' => 'Otro', 'apellidos' => 'Visitante', 'fecha_nacimiento' => '1990-01-01',
            'genero' => 'Hombre', 'telefono' => '9610000001', 'correo_verificado_en' => now(),
        ]);

        $cotizacion = app(CotizarCompraService::class)->cotizar(
            [['id_rubro' => $this->rubro->id, 'cant_hombre' => 1, 'cant_mujer' => 0]], $otraFecha,
        );
        $compra = app(RegistrarCompraService::class)->registrar($cliente, $cotizacion, $otraFecha);

        app(ConfirmarPagoService::class)->confirmar(new NotificacionPago(
            firmaValida: true, referenciaExterna: 'SIM-OTRA', estado: 'aprobado',
            montoCentavos: $compra->total_centavos, autorizacion: 'A', payload: ['folio' => $compra->folio],
        ), 'simulada');

        $resultado = $this->validar($compra->refresh()->qr_token);

        $this->assertFalse($resultado->permitido);
        $this->assertSame(ResultadoAcceso::FECHA_DISTINTA, $resultado->motivo);
    }

    public function test_una_compra_sin_pagar_no_deja_entrar(): void
    {
        $compra = $this->compraPagada(2);
        $token  = $compra->qr_token;

        $compra->update(['estado' => Compra::PENDIENTE_PAGO]);

        $resultado = $this->validar($token);

        $this->assertFalse($resultado->permitido);
        $this->assertSame(ResultadoAcceso::NO_PAGADA, $resultado->motivo);
    }

    public function test_una_compra_cancelada_no_deja_entrar(): void
    {
        $compra = $this->compraPagada(2);
        $compra->update(['estado' => Compra::CANCELADA]);

        $resultado = $this->validar($compra->qr_token);

        $this->assertFalse($resultado->permitido);
        $this->assertSame(ResultadoAcceso::CANCELADA, $resultado->motivo);
    }

    public function test_un_token_viejo_tras_reemplazo_se_rechaza(): void
    {
        $compra = $this->compraPagada(2);
        $viejo  = $compra->qr_token;

        // Simula un reagendado: se emitió un token nuevo.
        $compra->update(['qr_token' => $viejo . 'NUEVO']);

        $resultado = $this->validar($viejo);

        $this->assertFalse($resultado->permitido);
        $this->assertSame(ResultadoAcceso::QR_REEMPLAZADO, $resultado->motivo);
    }

    // ═══ Bitácora ═══

    public function test_todo_escaneo_queda_registrado_incluidos_los_rechazos(): void
    {
        $compra = $this->compraPagada(1);

        $this->validar($compra->qr_token, 1);   // permitido
        $this->validar($compra->qr_token, 1);   // rechazado: sin pases
        $this->validar('basura-total');         // rechazado: token inválido

        $this->assertSame(3, Acceso::count());
        $this->assertSame(1, Acceso::where('resultado', Acceso::PERMITIDO)->count());
        $this->assertSame(2, Acceso::where('resultado', Acceso::RECHAZADO)->count());

        // Un token falsificado no corresponde a ninguna compra, pero el
        // intento igual queda registrado.
        $this->assertSame(1, Acceso::whereNull('id_compra')->count());
    }

    public function test_el_registro_guarda_el_operador_y_el_torniquete(): void
    {
        $compra   = $this->compraPagada(1);
        $operador = Cuenta::where('username', 'admin')->firstOrFail();

        app(ValidarAccesoService::class)->validar($compra->qr_token, 1, 'T-99', $operador);

        $acceso = Acceso::latest('id')->firstOrFail();
        $this->assertSame('T-99', $acceso->id_torniquete);
        $this->assertSame($operador->id_cuenta, $acceso->id_cuenta);
        $this->assertSame(1, $acceso->pases_consumidos);
    }
}
