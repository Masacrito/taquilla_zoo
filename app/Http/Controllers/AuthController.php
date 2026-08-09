<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // 1) Existencia
        $cuenta = Cuenta::where('username', $request->username)->first();
        if (!$cuenta) {
            return back()->withErrors(['username' => 'Usuario no encontrado.'])
                         ->withInput($request->only('username'));
        }

        // 2) Estado activo
        if ($cuenta->estado === 'inactivo') {
            return back()->withErrors([
                'username' => 'Esta cuenta está desactivada. Contacta al administrador.'
            ])->withInput($request->only('username'));
        }

        // 3) Password
        if (Auth::attempt([
            'username' => $request->username,
            'password' => $request->password,
        ])) {
            $request->session()->regenerate();

            // Cargar permisos en sesión
            $permisos = $cuenta->fresh()->permisosArray();
            session(['permisos_usuario' => $permisos]);

            return $this->redirectByRole(Auth::user());
        }

        return back()->withErrors(['username' => 'Contraseña incorrecta.'])
                     ->withInput($request->only('username'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    /**
     * Redirección dinámica por nombre de rol.
     * Cualquier rol nuevo con nombre "Foo" manda a la ruta foo.dashboard,
     * así que agregar un rol no requiere tocar este método.
     */
    protected function redirectByRole($user)
    {
        $rol = strtolower(optional($user->rol)->nombre ?? '');

        $ruta = match ($rol) {
            'administrador' => 'admin.dashboard',
            default         => $rol . '.dashboard',
        };

        // Si el rol no tiene dashboard registrado, no dejamos la sesión colgada.
        if ($rol === '' || !Route::has($ruta)) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return redirect()->route('login')->withErrors([
                'username' => 'Tu rol no tiene una pantalla asignada. Contacta al administrador.',
            ]);
        }

        return redirect()->route($ruta);
    }
}
