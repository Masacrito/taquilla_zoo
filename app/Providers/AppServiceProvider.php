<?php

namespace App\Providers;

use App\Models\Cuenta;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registrarPermisosComoGates();
    }

    /**
     * Hace que `@can('editar_rubros')` y `$user->can(...)` consulten la tabla
     * rol_permiso, sin tener que declarar un Gate por cada permiso.
     *
     * Se resuelve en tiempo de petición, no al arrancar: no consulta la base
     * durante el boot, así que `migrate:fresh` sigue funcionando con la base
     * vacía.
     *
     * Misma regla que el middleware `permission`: el Administrador pasa
     * siempre. Los clientes del portal público nunca: no tienen permisos.
     */
    private function registrarPermisosComoGates(): void
    {
        Gate::before(function ($usuario, string $permiso) {
            if (! $usuario instanceof Cuenta) {
                return null;
            }

            if ($usuario->isAdmin()) {
                return true;
            }

            // null (y no false) para no cortar otros Gates que se definan luego.
            return $usuario->tienePermiso($permiso) ? true : null;
        });
    }
}
