<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión en caliente si un Administrador desactiva la cuenta
 * mientras la persona navega.
 *
 * Aplica SOLO al guard `web` (personal interno). El portal público usa el
 * guard `cliente`, cuyos usuarios no tienen columna `estado` — por eso el
 * guard va explícito y no `Auth::check()` a secas (brief §3.2).
 */
class CheckAccountStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if ($guard->check() && $guard->user()->estado === 'inactivo') {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'
            ]);
        }

        return $next($request);
    }
}
