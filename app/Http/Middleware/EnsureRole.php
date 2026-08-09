<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class EnsureRole
{
    /**
     * Uso:
     *   ->middleware('rol:Administrador')
     *   ->middleware('rol:Administrador,Taquilla')
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Aceptar "Administrador,Taquilla" como un solo argumento separado por coma
        if (count($roles) === 1 && is_string($roles[0]) && str_contains($roles[0], ',')) {
            $roles = array_map('trim', explode(',', $roles[0]));
        }

        $rolNombre = strtolower(optional($user->rol)->nombre ?? '');
        if ($rolNombre === '') {
            abort(403);
        }

        $roles = array_map(fn($r) => strtolower(trim($r)), $roles);

        if (!in_array($rolNombre, $roles, true)) {
            return redirect()->route($this->dashboardDe($rolNombre))
                ->with('error', 'No tienes acceso a esa sección.');
        }

        return $next($request);
    }

    /**
     * Ruta del dashboard propio del rol. Administrador es el único alias
     * (se llama admin.dashboard); el resto sigue el patrón <rol>.dashboard.
     */
    private function dashboardDe(string $rolNombre): string
    {
        $ruta = $rolNombre === 'administrador' ? 'admin.dashboard' : $rolNombre . '.dashboard';

        return Route::has($ruta) ? $ruta : 'login';
    }
}
