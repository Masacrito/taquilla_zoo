<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta el cruce entre las dos poblaciones del sistema (brief §3.2):
 * un cliente jamás alcanza el panel interno, y una cuenta interna jamás
 * puede comprar.
 *
 * Uso:
 *   ->middleware('guard.exclusivo:web')       en rutas internas
 *   ->middleware('guard.exclusivo:cliente')   en el portal público
 *
 * Responde 403, no un redirect: quien llega aquí ya está autenticado en el
 * guard equivocado, así que mandarlo al login no resolvería nada. El caso de
 * "no autenticado" lo maneja el middleware `auth`.
 */
class EnsureGuardExclusivo
{
    /** Todos los guards de sesión del sistema. */
    private const GUARDS = ['web', 'cliente'];

    public function handle(Request $request, Closure $next, string $guardPermitido): Response
    {
        foreach (self::GUARDS as $guard) {
            if ($guard !== $guardPermitido && Auth::guard($guard)->check()) {
                abort(403, 'Esta sección no corresponde a tu tipo de cuenta.');
            }
        }

        return $next($request);
    }
}
