<?php

namespace App\Services\Cliente;

use App\Mail\CodigoVerificacion;
use App\Models\VerificacionCorreo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Códigos de verificación de correo (brief §5.3).
 *
 * Código de 6 dígitos, vigencia 10 minutos, máximo 3 reenvíos.
 * Se guarda el HASH, nunca el código: quien lea la tabla no puede verificar
 * cuentas ajenas.
 */
class VerificacionCorreoService
{
    /**
     * Emite un código nuevo e invalida los anteriores del mismo correo.
     *
     * @throws ValidationException si se excedió el máximo de reenvíos
     */
    public function emitir(string $correo): string
    {
        $correo = mb_strtolower(trim($correo));

        $this->verificarLimiteDeReenvios($correo);

        // Un solo código vivo por correo: los previos se consumen.
        VerificacionCorreo::where('correo', $correo)
            ->whereNull('consumido_en')
            ->update(['consumido_en' => now()]);

        // random_int es criptográficamente seguro; rand() no.
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        VerificacionCorreo::create([
            'correo'      => $correo,
            'codigo_hash' => Hash::make($codigo),
            'intentos'    => 0,
            'expira_en'   => now()->addMinutes(VerificacionCorreo::MINUTOS_VIGENCIA),
            'created_at'  => now(),
        ]);

        return $codigo;
    }

    /**
     * Valida el código. Devuelve true solo si coincide, está vigente y no se
     * agotaron los intentos.
     *
     * @throws ValidationException
     */
    public function validar(string $correo, string $codigo): bool
    {
        $correo = mb_strtolower(trim($correo));

        $verificacion = VerificacionCorreo::where('correo', $correo)
            ->whereNull('consumido_en')
            ->latest('id')
            ->first();

        if (! $verificacion) {
            throw ValidationException::withMessages([
                'codigo' => 'No hay un código pendiente para ese correo. Solicita uno nuevo.',
            ]);
        }

        if (! $verificacion->vigente()) {
            throw ValidationException::withMessages([
                'codigo' => 'El código expiró. Solicita uno nuevo.',
            ]);
        }

        if ($verificacion->intentos >= VerificacionCorreo::MAX_INTENTOS) {
            // Se quema el código: evita la fuerza bruta sobre 6 dígitos.
            $verificacion->update(['consumido_en' => now()]);

            throw ValidationException::withMessages([
                'codigo' => 'Demasiados intentos fallidos. Solicita un código nuevo.',
            ]);
        }

        if (! $verificacion->coincide($codigo)) {
            $verificacion->increment('intentos');

            throw ValidationException::withMessages([
                'codigo' => 'El código no es correcto.',
            ]);
        }

        $verificacion->update(['consumido_en' => now()]);

        return true;
    }

    /**
     * Máximo 3 códigos por correo dentro de la ventana de vigencia (§5.3).
     */
    private function verificarLimiteDeReenvios(string $correo): void
    {
        $emitidos = VerificacionCorreo::where('correo', $correo)
            ->where('created_at', '>=', now()->subMinutes(VerificacionCorreo::MINUTOS_VIGENCIA))
            ->count();

        if ($emitidos >= VerificacionCorreo::MAX_REENVIOS) {
            throw ValidationException::withMessages([
                'correo' => 'Ya se enviaron demasiados códigos a ese correo. Espera unos minutos.',
            ]);
        }
    }

    /**
     * Entrega del código por correo, en cola.
     *
     * Con MAIL_MAILER=log el mensaje termina en storage/logs/laravel.log, que
     * es suficiente para desarrollo. Para producción hay que configurar un
     * SMTP real (ver README-AUTH.md).
     */
    public function entregar(string $correo, string $codigo): void
    {
        Mail::to($correo)->queue(
            new CodigoVerificacion($codigo, VerificacionCorreo::MINUTOS_VIGENCIA)
        );
    }
}
