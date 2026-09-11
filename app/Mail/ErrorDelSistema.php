<?php

namespace App\Mail;

use App\Models\ErrorSistema;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al Super Admin de que algo se rompió.
 *
 * No trae la traza completa a propósito: el correo es para enterarse, no para
 * diagnosticar. El detalle vive en /admin/errores, detrás de la sesión, que
 * es donde debe estar — una traza de Laravel en un buzón enseña rutas del
 * servidor y estructura interna a quien alcance ese correo.
 */
class ErrorDelSistema extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ErrorSistema $error)
    {
    }

    public function envelope(): Envelope
    {
        $donde = config('app.url');

        return new Envelope(
            subject: "Falla en el sistema de taquilla — {$this->error->claseCorta()} ({$donde})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.error-sistema',
            with: [
                'error' => $this->error,
                'url'   => route('admin.errores.index'),
            ],
        );
    }
}
