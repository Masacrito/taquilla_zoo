<?php

namespace App\Http\Middleware;

use App\Services\Cliente\SesionInvitado;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja comprar a quien tiene cuenta o a quien verificó su correo como invitado.
 *
 * Sustituye a `auth:cliente` solo en las rutas de compra. A quien no es
 * ninguna de las dos cosas lo manda a elegir cómo quiere comprar, no al login
 * del personal (que es a donde lo mandaba `auth` por omisión).
 *
 * Va siempre después de `guard.exclusivo:cliente`: una cuenta interna sigue
 * recibiendo 403 antes de llegar aquí (brief §3.2).
 */
class EnsureComprador
{
    public function __construct(private readonly SesionInvitado $invitado)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('cliente')->check() || $this->invitado->correo() !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(401);
        }

        return redirect()->route('compras.acceso');
    }
}
