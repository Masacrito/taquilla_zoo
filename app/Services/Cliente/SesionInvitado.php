<?php

namespace App\Services\Cliente;

use Illuminate\Contracts\Session\Session;

/**
 * El correo verificado de quien compra sin cuenta, guardado en su sesión.
 *
 * No es un guard ni un usuario: el invitado no tiene rol, permisos ni fila
 * en `clientes`. Lo único que se recuerda es que este navegador demostró,
 * con un código, que controla un buzón; eso basta para comprar y caduca solo.
 *
 * Tiene dos momentos: `pendiente` (ya pidió el código, falta escribirlo) y
 * verificado. El correo pendiente vive en sesión para que el formulario del
 * código no lo reciba del navegador.
 */
class SesionInvitado
{
    private const PENDIENTE  = 'invitado_pendiente';
    private const VERIFICADO = 'invitado';

    public function __construct(private readonly Session $sesion)
    {
    }

    public function esperarCodigo(string $correo): void
    {
        $this->sesion->put(self::PENDIENTE, $correo);
    }

    public function correoPendiente(): ?string
    {
        return $this->sesion->get(self::PENDIENTE);
    }

    public function iniciar(string $correo): void
    {
        $this->sesion->forget(self::PENDIENTE);
        $this->sesion->put(self::VERIFICADO, ['correo' => $correo, 'verificado_en' => now()->timestamp]);
    }

    /** El correo verificado, o null si no hay o ya caducó. */
    public function correo(): ?string
    {
        $invitado = $this->sesion->get(self::VERIFICADO);

        if (! is_array($invitado)) {
            return null;
        }

        $vigencia = (int) config('taquilla.compra.minutos_sesion_invitado') * 60;

        if (now()->timestamp - (int) $invitado['verificado_en'] > $vigencia) {
            $this->sesion->forget(self::VERIFICADO);

            return null;
        }

        return $invitado['correo'];
    }
}
