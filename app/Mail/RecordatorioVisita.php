<?php

namespace App\Mail;

use App\Jobs\EnviarRecordatoriosDeVisita;
use App\Models\Compra;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Recordatorio de visita (2026-10-05): «tu visita es mañana» o «es hoy».
 *
 * No repite el QR ni el PDF: eso ya llegó en el comprobante. Lleva el botón
 * a la compra, que es donde está el código.
 *
 * Lo encola App\Jobs\EnviarRecordatoriosDeVisita.
 */
class RecordatorioVisita extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Compra $compra, public string $tipo)
    {
    }

    public function esHoy(): bool
    {
        return $this->tipo === EnviarRecordatoriosDeVisita::MISMO_DIA;
    }

    public function envelope(): Envelope
    {
        $cuando = $this->esHoy() ? 'hoy' : 'mañana';

        return new Envelope(
            subject: "Tu visita al ZooMAT es {$cuando} — {$this->compra->folio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recordatorio',
            with: [
                'compra'  => $this->compra,
                'esHoy'   => $this->esHoy(),
                'horario' => config('taquilla.horario'),
            ],
        );
    }
}
