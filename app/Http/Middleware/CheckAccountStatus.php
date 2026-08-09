<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $cuenta = Auth::user();
            if ($cuenta->estado === 'inactivo') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')->withErrors([
                    'username' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'
                ]);
            }
        }
        return $next($request);
    }
}
