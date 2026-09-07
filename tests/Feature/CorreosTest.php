<?php

namespace Tests\Feature;

use App\Mail\CodigoVerificacion;
use App\Mail\ComprobanteCompra;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Nacionalidad;
use App\Models\Pago;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Services\Cliente\VerificacionCorreoService;
use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\NotificacionPago;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CorreosTest extends TestCase
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
            'precio_centavos'    => 4000,
            'vigente_desde'      => now()->subMonth(),
            'activo'             => true,
        ]);

        $this->fechaVisita = Carbon::parse('next tuesday')->toDateString();
        AforoDiario::create(['fecha' => $this->fechaVisita]);
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'correo'               => 'ana@example.com',
            'password'             => Hash::make('secreto12345'),
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
        ]);
    }

    private function comprarYPagar(): Compra
    {
        $cliente = $this->cliente();

        $this->actingAs($cliente, 'cliente')->post('/comprar', [
            'fecha_visita' => $this->fechaVisita,
            'renglones'    => [['id_rubro' => $this->rubro->id, 'cant_hombre' => 1, 'cant_mujer' => 1]],
        ]);

        $compra = Compra::firstOrFail();

        app(ConfirmarPagoService::class)->confirmar(new NotificacionPago(
            firmaValida: true,
            referenciaExterna: Pago::where('id_compra', $compra->id)->value('referencia_externa'),
            estado: 'aprobado',
            montoCentavos: $compra->total_centavos,
            autorizacion: 'AUTH1',
            payload: ['folio' => $compra->folio],
        ), 'simulada');

        return $compra->refresh();
    }

    // ═══ Código de verificación ═══

    public function test_el_codigo_de_verificacion_se_encola(): void
    {
        Mail::fake();

        $servicio = app(VerificacionCorreoService::class);
        $codigo   = $servicio->emitir('nueva@example.com');
        $servicio->entregar('nueva@example.com', $codigo);

        Mail::assertQueued(CodigoVerificacion::class, function ($mail) use ($codigo) {
            return $mail->codigo === $codigo
                && $mail->hasTo('nueva@example.com');
        });
    }

    public function test_el_registro_dispara_el_correo_del_codigo(): void
    {
        Mail::fake();

        $this->post('/registro', [
            'correo'                => 'nueva@example.com',
            'password'              => 'secreto12345',
            'password_confirmation' => 'secreto12345',
            'nombre'                => 'Nueva',
            'apellidos'             => 'Visitante',
            'fecha_nacimiento'      => '1995-03-10',
            'genero'                => 'Mujer',
            'telefono'              => '9610001111',
        ]);

        Mail::assertQueued(CodigoVerificacion::class);
    }

    // ═══ Comprobante ═══

    public function test_el_pago_confirmado_encola_el_comprobante(): void
    {
        Mail::fake();

        $compra = $this->comprarYPagar();

        Mail::assertQueued(ComprobanteCompra::class, function ($mail) use ($compra) {
            return $mail->compra->id === $compra->id
                && $mail->hasTo('ana@example.com');
        });
    }

    public function test_un_webhook_reenviado_no_manda_el_comprobante_dos_veces(): void
    {
        Mail::fake();

        $compra    = $this->comprarYPagar();
        $referencia = Pago::where('id_compra', $compra->id)->value('referencia_externa');

        // El banco reenvía el mismo webhook dos veces más.
        foreach (range(1, 2) as $reintento) {
            app(ConfirmarPagoService::class)->confirmar(new NotificacionPago(
                firmaValida: true,
                referenciaExterna: $referencia,
                estado: 'aprobado',
                montoCentavos: $compra->total_centavos,
                autorizacion: 'AUTH1',
                payload: ['folio' => $compra->folio],
            ), 'simulada');
        }

        Mail::assertQueuedCount(1);
    }

    public function test_un_pago_rechazado_no_manda_comprobante(): void
    {
        Mail::fake();

        $cliente = $this->cliente();
        $this->actingAs($cliente, 'cliente')->post('/comprar', [
            'fecha_visita' => $this->fechaVisita,
            'renglones'    => [['id_rubro' => $this->rubro->id, 'cant_hombre' => 1, 'cant_mujer' => 0]],
        ]);

        $compra = Compra::firstOrFail();

        app(ConfirmarPagoService::class)->confirmar(new NotificacionPago(
            firmaValida: true,
            referenciaExterna: Pago::where('id_compra', $compra->id)->value('referencia_externa'),
            estado: 'rechazado',
            montoCentavos: $compra->total_centavos,
            autorizacion: null,
            payload: ['folio' => $compra->folio],
        ), 'simulada');

        Mail::assertNotQueued(ComprobanteCompra::class);
    }

    /**
     * Sin Mail::fake: se construye el mensaje de verdad, con su HTML y su
     * adjunto.
     */
    public function test_el_comprobante_se_arma_con_el_qr_y_su_pdf_adjunto(): void
    {
        $compra = $this->comprarYPagar();

        $mailable = new ComprobanteCompra($compra);
        $mailable->assertHasSubject("Tu comprobante ZooMAT — {$compra->folio}");
        $mailable->assertSeeInHtml($compra->folio);
        $mailable->assertSeeInHtml('Lunes cerrado');

        // El QR NO puede ir como SVG: Gmail lo elimina y el visitante
        // recibiría el correo sin su código.
        $mailable->assertDontSeeInHtml('<svg', false);
        $mailable->assertSeeInHtml('<img', false);

        // attachments() ya construye el PDF con Pdf::loadView; si la vista o
        // el QR fallaran, reventaría aquí.
        $adjuntos = $mailable->attachments();
        $this->assertCount(1, $adjuntos);
        $this->assertSame("comprobante-{$compra->folio}.pdf", $adjuntos[0]->as);
        $this->assertSame('application/pdf', $adjuntos[0]->mime);
    }

    /**
     * Inspecciona el mensaje MIME que de verdad sale, no la previsualización.
     *
     * Al renderizar en aislamiento, Laravel convierte las imágenes incrustadas
     * a data URI para que la vista previa se vea bien; solo al enviar se
     * generan las partes `cid:` reales. Esta prueba mira el envío.
     */
    public function test_el_correo_enviado_lleva_el_qr_como_imagen_incrustada(): void
    {
        // Sin Mail::fake, y con la cola en modo síncrono, confirmar el pago
        // envía el comprobante de verdad: se inspecciona ese, no uno armado
        // a mano para la prueba.
        $compra = $this->comprarYPagar();

        $mensajes = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $mensajes, 'La confirmación de pago debe enviar exactamente un comprobante.');

        $enviado = $mensajes[0]->getOriginalMessage();
        $this->assertStringContainsString($compra->folio, $enviado->getSubject());
        $tipos   = collect($enviado->getAttachments())
            ->map(fn ($parte) => $parte->getMediaType() . '/' . $parte->getMediaSubtype())
            ->all();

        // Un PNG (el QR, en línea) y un PDF (el comprobante adjunto).
        $this->assertContains('image/png', $tipos, 'Falta el QR incrustado.');
        $this->assertContains('application/pdf', $tipos, 'Falta el PDF adjunto.');

        $this->assertStringContainsString('cid:', $enviado->getHtmlBody());
    }

    /**
     * El binario del PDF, por la vía directa: ejercita dompdf, la plantilla y
     * el rasterizado del QR con GD. Si alguno falla, esta prueba truena.
     */
    public function test_el_pdf_del_comprobante_se_genera_de_verdad(): void
    {
        $compra = $this->comprarYPagar();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.comprobante', [
            'compra' => $compra->load('detalle'),
            'qrPng'  => app(\App\Services\Acceso\QrImagenService::class)->pngDataUri($compra),
        ])->setPaper('letter')->output();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf), 'El PDF parece vacío.');
    }
}
