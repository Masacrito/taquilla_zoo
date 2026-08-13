<?php

namespace App\Mail;

use App\Models\Compra;
use App\Services\Acceso\QrImagenService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Comprobante de compra con el código QR (brief §9, Fase 2).
 *
 * Va EN COLA (ShouldQueue): generar el PDF y hablar con el servidor SMTP
 * puede tardar segundos, y el webhook del banco no puede quedarse esperando
 * — si tarda demasiado, el banco lo da por fallido y lo reenvía.
 *
 * Requiere un worker corriendo:  php artisan queue:work
 */
class ComprobanteCompra extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Compra $compra)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu comprobante ZooMAT — {$this->compra->folio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.comprobante',
            with: [
                'compra' => $this->compra->loadMissing('detalle'),
                'qrSvg'  => app(QrImagenService::class)->svg($this->compra, 220),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.comprobante', [
            'compra' => $this->compra->loadMissing('detalle'),
            'qrPng'  => app(QrImagenService::class)->pngDataUri($this->compra),
        ])->setPaper('letter');

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                "comprobante-{$this->compra->folio}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
