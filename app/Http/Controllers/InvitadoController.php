<?php

namespace App\Http\Controllers;

use App\Models\VerificacionCorreo;
use App\Services\Cliente\SesionInvitado;
use App\Services\Cliente\VerificacionCorreoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Compra sin cuenta: elegir cómo comprar y verificar el correo del invitado.
 *
 * El invitado solo da un correo. Se verifica con el mismo código de 6 dígitos
 * del registro (brief §5.3), con propósito propio para que no sirva de
 * puerta a ninguna cuenta. La compra en sí la sigue haciendo CompraController.
 */
class InvitadoController extends Controller
{
    public function __construct(
        private readonly VerificacionCorreoService $verificacion,
        private readonly SesionInvitado $invitado,
    ) {
    }

    /** «¿Con tu cuenta o como invitado?» */
    public function acceso()
    {
        if (Auth::guard('cliente')->check() || $this->invitado->correo() !== null) {
            return redirect()->route('compras.crear');
        }

        return view('publico.acceso');
    }

    public function solicitar(Request $request)
    {
        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:160'],
        ]);

        $this->enviarCodigo(mb_strtolower(trim($datos['correo'])));

        return redirect()->route('invitado.verificar')
            ->with('success', 'Te enviamos un código de 6 dígitos. Revisa tu correo.');
    }

    public function mostrarVerificacion()
    {
        $correo = $this->invitado->correoPendiente();

        if ($correo === null) {
            return redirect()->route('compras.acceso');
        }

        return view('publico.auth.verificar', [
            'correo'        => $correo,
            'rutaVerificar' => route('invitado.verificar'),
            'rutaReenviar'  => route('invitado.reenviar'),
        ]);
    }

    public function verificar(Request $request)
    {
        $datos = $request->validate(['codigo' => ['required', 'digits:6']]);

        $correo = $this->invitado->correoPendiente();

        if ($correo === null) {
            return redirect()->route('compras.acceso');
        }

        $this->verificacion->validar($correo, $datos['codigo'], VerificacionCorreo::INVITADO);

        $this->invitado->iniciar($correo);
        $request->session()->regenerate();

        return redirect()->route('compras.crear')
            ->with('success', 'Tu correo quedó verificado. Ya puedes comprar.');
    }

    public function reenviar()
    {
        $correo = $this->invitado->correoPendiente();

        if ($correo === null) {
            return redirect()->route('compras.acceso');
        }

        $this->enviarCodigo($correo);

        return back()->with('success', 'Te enviamos un código nuevo.');
    }

    private function enviarCodigo(string $correo): void
    {
        $codigo = $this->verificacion->emitir($correo, VerificacionCorreo::INVITADO);

        $this->invitado->esperarCodigo($correo);
        $this->verificacion->entregar($correo, $codigo, VerificacionCorreo::INVITADO);
    }
}
