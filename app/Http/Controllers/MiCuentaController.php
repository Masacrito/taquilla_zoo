<?php

namespace App\Http\Controllers;

use App\Services\Auditoria\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Lo que cada cuenta interna puede cambiar de sí misma.
 *
 * La gestión de usuarios prohíbe editarse a uno mismo, y al Super Admin no lo
 * edita nadie más: sin esta pantalla, su contraseña inicial y su correo
 * `@example.com` eran permanentes. Rol, estado y username no se tocan aquí;
 * eso sigue siendo cosa de quien administra las cuentas.
 */
class MiCuentaController extends Controller
{
    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    public function editar()
    {
        return view('cuenta.editar', ['cuenta' => Auth::user()->load('usuario')]);
    }

    public function actualizar(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'email'  => ['nullable', 'email', 'max:160'],
        ], [], ['email' => 'correo']);

        $usuario  = Auth::user()->usuario;
        $anterior = $usuario->getOriginal();

        $usuario->update($datos);
        $this->bitacora->actualizado($usuario, $anterior);

        return back()->with('success', 'Tus datos quedaron actualizados.');
    }

    public function cambiarPassword(Request $request)
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'current_password:web'],
            'password'        => ['required', 'confirmed', 'different:password_actual',
                                  Password::min(10)->letters()->numbers()],
        ], [
            'password_actual.current_password' => 'La contraseña actual no coincide.',
            'password.different'               => 'La contraseña nueva debe ser distinta de la actual.',
        ], [
            'password_actual' => 'contraseña actual',
            'password'        => 'contraseña nueva',
        ]);

        $cuenta = Auth::user();
        $cuenta->password             = Hash::make($datos['password']);
        $cuenta->password_cambiado_en = now();
        $cuenta->save();

        // Quien conociera la clave anterior pierde su sesión. Solo aplica al
        // driver `database`, que es el de producción; con otro driver las
        // sesiones viejas mueren al expirar.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', (string) $cuenta->id_cuenta)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $this->bitacora->registrar('cuentas', BitacoraService::UPDATE, (string) $cuenta->id_cuenta, [
            'password_cambiado' => true,
        ]);

        return back()->with('success', 'Tu contraseña quedó cambiada.');
    }
}
