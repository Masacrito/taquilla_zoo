<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Enlace para elegir una contraseña nueva (2026-10-27). Solo clientes del
 * portal: el personal interno lo pide al Administrador.
 *
 * El correo no va en el enlace: la persona lo vuelve a escribir en el
 * formulario, así el enlace no expone su dirección si se reenvía o queda en
 * el historial.
 */
class RestablecerContrasena extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $token)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Restablece tu contraseña del ZooMAT');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.restablecer-contrasena',
            with: [
                'url'     => route('portal.restablecer', $this->token),
                'minutos' => config('auth.passwords.clientes.expire'),
            ],
        );
    }
}
