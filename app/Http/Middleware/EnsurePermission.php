<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class EnsurePermission
{
    /**
     * Uso:
     *   ->middleware('permission:crear_usuarios')                     (todos requeridos)
     *   ->middleware('permission:crear_usuarios,editar_usuarios')     (todos requeridos)
     *   ->middleware('permission:any,crear_usuarios,editar_usuarios') (al menos uno)
     */
    public function handle(Request $request, Closure $next, ...$params)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Administrador siempre pasa
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        $params = array_values(array_filter($params, fn($p) => $p !== null && $p !== ''));

        // Modo "any": basta con que tenga uno
        $modeAny = false;
        if (!empty($params) && strtolower($params[0]) === 'any') {
            $modeAny = true;
            array_shift($params);
        }

        // Aceptar "a,b,c" como argumento único
        if (count($params) === 1 && is_string($params[0]) && str_contains($params[0], ',')) {
            $params = array_map('trim', explode(',', $params[0]));
        }

        if (empty($params)) {
            return redirect()->route($this->dashboardDe($user))
                ->with('error', 'No tienes permisos suficientes');
        }

        $has = $modeAny ? false : true;

        foreach ($params as $perm) {
            $ok = $user->tienePermiso($perm);
            if ($modeAny) {
                if ($ok) { $has = true; break; }
            } else {
                if (!$ok) { $has = false; break; }
            }
        }

        if (!$has) {
            return redirect()->route($this->dashboardDe($user))
                ->with('error', 'No tienes permisos suficientes');
        }

        return $next($request);
    }

    private function dashboardDe($user): string
    {
        $rol  = strtolower(optional($user->rol)->nombre ?? '');
        $ruta = $rol === 'administrador' ? 'admin.dashboard' : $rol . '.dashboard';

        return ($rol !== '' && Route::has($ruta)) ? $ruta : 'login';
    }
}
