<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Código de 6 dígitos para verificar el correo (brief §5.3).
 *
 * El código viaja en el correo por necesidad, pero en la base solo existe
 * su hash: este objeto no se persiste.
 */
class CodigoVerificacion extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $codigo,
        public int $minutosVigencia,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu código de verificación: {$this->codigo}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.codigo-verificacion');
    }
}
