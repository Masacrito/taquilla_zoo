<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Verifica la configuración de correo sin tener que pasar por el registro.
 *
 * Manda el mensaje de forma SÍNCRONA (Mail::raw, no ->queue) para que los
 * errores del SMTP se vean aquí mismo, en vez de terminar en un job fallido.
 */
class ProbarCorreo extends Command
{
    protected $signature = 'taquilla:probar-correo {destinatario : Correo al que enviar la prueba}';

    protected $description = 'Envía un correo de prueba para verificar la configuración SMTP';

    public function handle(): int
    {
        $destinatario = $this->argument('destinatario');

        $this->newLine();
        $this->line('  Configuración actual:');
        $this->line('    MAIL_MAILER ....... ' . config('mail.default'));
        $this->line('    MAIL_HOST ......... ' . config('mail.mailers.smtp.host'));
        $this->line('    MAIL_PORT ......... ' . config('mail.mailers.smtp.port'));
        $this->line('    MAIL_SCHEME ....... ' . (config('mail.mailers.smtp.scheme') ?: '(sin definir)'));
        $this->line('    MAIL_USERNAME ..... ' . (config('mail.mailers.smtp.username') ?: '(vacío)'));
        $this->line('    MAIL_PASSWORD ..... ' . (filled(config('mail.mailers.smtp.password')) ? '(definida)' : '(vacía)'));
        $this->line('    MAIL_FROM_ADDRESS . ' . config('mail.from.address'));
        $this->newLine();

        if (config('mail.default') === 'log') {
            $this->warn('  MAIL_MAILER=log: el correo NO saldrá a internet.');
            $this->warn('  Se escribirá en storage/logs/laravel.log.');
            $this->newLine();
        }

        $this->info("  Enviando a {$destinatario}...");

        try {
            Mail::raw(
                "Prueba de configuración del sistema de taquilla del ZooMAT.\n\n"
                . 'Si recibiste este mensaje, el envío de correo funciona correctamente.',
                fn ($mensaje) => $mensaje->to($destinatario)->subject('Prueba de correo — ZooMAT'),
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('  Falló el envío: ' . $e->getMessage());
            $this->newLine();
            $this->line('  Causas frecuentes:');
            $this->line('    · Gmail exige una contraseña de aplicación, no la del correo.');
            $this->line('    · MAIL_FROM_ADDRESS debe ser la misma cuenta que MAIL_USERNAME.');
            $this->line('    · Puerto 587 con MAIL_SCHEME=smtp, o 465 con MAIL_SCHEME=smtps.');
            $this->line('    · Falta ejecutar: php artisan config:clear');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('  Enviado sin errores.');

        if (config('mail.default') === 'log') {
            $this->line('  Revisa storage/logs/laravel.log');
        } else {
            $this->line('  Revisa la bandeja de entrada (y la carpeta de spam).');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
