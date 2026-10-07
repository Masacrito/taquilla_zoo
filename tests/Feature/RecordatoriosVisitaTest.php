<?php

namespace Tests\Feature;

use App\Jobs\EnviarRecordatoriosDeVisita;
use App\Mail\RecordatorioVisita;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Recordatorios de visita (2026-10-05): la tarde anterior y la mañana del
 * mismo día, una sola vez cada uno y solo a quien de verdad va a ir.
 */
class RecordatoriosVisitaTest extends TestCase
{
    use RefreshDatabase;

    private const HOY    = '2026-10-14';
    private const MANANA = '2026-10-15';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::HOY . ' 18:00:00');
        Mail::fake();

        AforoDiario::create(['fecha' => self::HOY]);
        AforoDiario::create(['fecha' => self::MANANA]);
    }

    /** Una compra hecha ayer, salvo que se indique otra cosa. */
    private function compra(string $folio, string $fechaVisita, array $extra = []): Compra
    {
        return Compra::create([
            'folio'           => $folio,
            'correo_invitado' => "{$folio}@example.com",
            'fecha_compra'    => now()->subDay(),
            'fecha_visita'    => $fechaVisita,
            'total_centavos'  => 8000,
            'pases_total'     => 2,
            'estado'          => Compra::PAGADA,
            ...$extra,
        ]);
    }

    private function correr(string $tipo): void
    {
        (new EnviarRecordatoriosDeVisita($tipo))->handle();
    }

    public function test_la_vispera_avisa_a_las_visitas_de_manana_y_el_mismo_dia_a_las_de_hoy(): void
    {
        $manana = $this->compra('zm-manana', self::MANANA);
        $hoy    = $this->compra('zm-hoy', self::HOY);

        $this->correr(EnviarRecordatoriosDeVisita::VISPERA);

        Mail::assertQueued(RecordatorioVisita::class, 1);
        Mail::assertQueued(RecordatorioVisita::class,
            fn (RecordatorioVisita $mail) => $mail->compra->is($manana) && ! $mail->esHoy() && $mail->hasTo('zm-manana@example.com'));

        $this->correr(EnviarRecordatoriosDeVisita::MISMO_DIA);

        Mail::assertQueued(RecordatorioVisita::class, 2);
        Mail::assertQueued(RecordatorioVisita::class,
            fn (RecordatorioVisita $mail) => $mail->compra->is($hoy) && $mail->esHoy());

        $this->assertNotNull($manana->refresh()->recordatorio_vispera_en);
        $this->assertNull($manana->recordatorio_dia_en);
    }

    public function test_cada_aviso_sale_una_sola_vez(): void
    {
        $this->compra('zm-1', self::MANANA);

        $this->correr(EnviarRecordatoriosDeVisita::VISPERA);
        $this->correr(EnviarRecordatoriosDeVisita::VISPERA);

        Mail::assertQueued(RecordatorioVisita::class, 1);
    }

    public function test_no_se_avisa_a_quien_no_va_a_ir_ni_a_quien_acaba_de_comprar(): void
    {
        $this->compra('zm-sin-pagar', self::MANANA, ['estado' => Compra::PENDIENTE_PAGO]);
        $this->compra('zm-cancelada', self::MANANA, ['estado' => Compra::CANCELADA]);
        $this->compra('zm-ya-entro', self::MANANA, ['estado' => Compra::ACCESO_PARCIAL, 'pases_usados' => 1]);
        // Comprada hoy mismo: acaba de recibir su comprobante.
        $this->compra('zm-reciente', self::MANANA, ['fecha_compra' => now()->subHour()]);

        $this->correr(EnviarRecordatoriosDeVisita::VISPERA);

        Mail::assertNothingQueued();
    }

    public function test_si_el_dia_se_cerro_no_se_avisa(): void
    {
        $this->compra('zm-1', self::MANANA);
        AforoDiario::where('fecha', self::MANANA)->update(['cerrado' => true]);

        $this->correr(EnviarRecordatoriosDeVisita::VISPERA);

        Mail::assertNothingQueued();
    }

    public function test_el_correo_se_arma_para_cliente_con_cuenta(): void
    {
        $cliente = Cliente::create([
            'correo'               => 'ana@example.com',
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
        ]);

        $compra = $this->compra('ZM-2026-000001', self::HOY, ['id_cliente' => $cliente->id, 'correo_invitado' => null]);

        $mail = new RecordatorioVisita($compra, EnviarRecordatoriosDeVisita::MISMO_DIA);

        $mail->assertHasSubject('Tu visita al ZooMAT es hoy — ZM-2026-000001');
        $mail->assertSeeInHtml('Hola Ana');
        $mail->assertSeeInHtml(config('taquilla.horario.cierre'));
    }
}
