# Sistema de autenticación y RBAC

## Stack

- Laravel 12 (middlewares registrados en `bootstrap/app.php`)
- Login por **username** (no email)
- Roles: **Administrador**, **Taquilla**, **Visitante**
- 7 permisos granulares vía tabla pivote `rol_permiso`
- Auditoría de movimientos habilitada (tabla `movimientos` + `App\Models\Movimiento`)
- Sin paquetes externos (no usa `spatie/laravel-permission`)

## Primer arranque

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Acceder en `http://127.0.0.1:8000` con:

| username | password | rol           |
|----------|----------|---------------|
| admin    | admin123 | Administrador |

> ⚠️ Cambia esta contraseña inmediatamente desde el panel admin → Gestionar usuarios → Editar datos.

El seeder solo crea al Super Admin. Las cuentas de **Taquilla** y **Visitante** se crean desde
el panel (`/admin/users` → *+ Nuevo usuario*).

## Estructura de rutas

| Ruta                                  | Protección                                              |
|---------------------------------------|---------------------------------------------------------|
| `/`                                   | login (GET muestra el form, POST autentica)             |
| `/admin/dashboard`                    | `rol:Administrador`                                     |
| `/taquilla/dashboard`                 | `rol:Taquilla`                                          |
| `/visitante/dashboard`                | `rol:Visitante`                                         |
| `/admin/users`                        | `permission:any,gestion_usuarios,crear_usuarios,editar_usuarios` |
| `/admin/users` (POST)                 | `permission:crear_usuarios`                             |
| `/admin/users/{id}` (PUT)             | `permission:editar_usuarios`                            |
| `/admin/users/{id}/update-role`       | `permission:cambiar_roles`                              |
| `/admin/users/{id}/update-permissions`| `permission:gestion_permisos`                           |
| `/admin/users/{id}/toggle-status`     | `permission:activar_cuentas`                            |
| `/admin/users/{id}` (DELETE)          | `permission:eliminar_usuarios`                          |

Los **dashboards** van por rol; la **gestión de usuarios** va por permiso.

## Permisos base

| id | nombre             | descripción                        |
|----|--------------------|------------------------------------|
| 1  | gestion_usuarios   | Ver listado de usuarios            |
| 2  | crear_usuarios     | Crear nuevos usuarios              |
| 3  | editar_usuarios    | Editar datos de usuarios           |
| 4  | eliminar_usuarios  | Eliminar usuarios                  |
| 5  | cambiar_roles      | Cambiar rol de un usuario          |
| 6  | activar_cuentas    | Activar / desactivar cuentas       |
| 7  | gestion_permisos   | Editar permisos asignados a un rol |

Asignación inicial: **Administrador** recibe los 7. **Taquilla** y **Visitante** arrancan sin
permisos; se les asignan desde el panel (los permisos son **por rol**, no por cuenta: marcarlos
para una cuenta de Taquilla los aplica a todas las cuentas con ese rol).

## Cómo verificar permisos en código

```php
// En un controlador
if (! auth()->user()->tienePermiso('crear_usuarios')) {
    abort(403);
}
```

```blade
@if (auth()->user()->isAdmin())
    ...
@endif

@if (auth()->user()->tieneRol('Taquilla'))
    ...
@endif

@if (auth()->user()->tienePermiso('activar_cuentas'))
    ...
@endif
```

Helpers semánticos disponibles en `App\Models\Cuenta`: `isAdmin()`, `isTaquilla()`,
`isVisitante()`, `tieneRol()`, `tienePermiso()`, `puedeGestionarUsuarios()`,
`puedeCrearUsuarios()`, `puedeEditarUsuarios()`, `puedeEliminarUsuarios()`,
`puedeCambiarRoles()`, `puedeActivarCuentas()`, `puedeGestionarPermisos()`.

## Auditoría

```php
use App\Models\Movimiento;

Movimiento::registrar(
    auth()->user()->id_cuenta,  // quién
    'boletos',                  // tabla afectada
    'CREATE',                   // CREATE | UPDATE | DELETE
    (string) $boleto->id,       // id del registro
    ['total' => 120.00]         // detalles (JSON)
);
```

El `AdminController` ya registra automáticamente: alta, edición, cambio de rol, cambio de
permisos, activar/desactivar y eliminación de cuentas.

## Reglas de Super Admin

El usuario con `id_usuario = 1` es el Super Administrador:

- **Solo él** puede asignar/quitar el rol Administrador.
- **Solo él** puede modificar a otros Administradores (datos, rol, estado, permisos).
- **Nunca** puede ser eliminado.
- **Ningún** usuario puede editarse / eliminarse / desactivarse a sí mismo.

## Cuentas desactivadas

El middleware `CheckAccountStatus` está aplicado globalmente al grupo `web`: si un admin
desactiva una cuenta mientras esa persona navega, su sesión se cierra en la siguiente petición.
El login también rechaza cuentas inactivas antes de comprobar la contraseña.

## Agregar un rol nuevo

1. Insertar en la tabla `roles`: `DB::table('roles')->insert(['nombre' => 'Supervisor']);`
2. `php artisan make:controller SupervisorController` con un método `dashboard()`.
3. Crear la vista `resources/views/supervisor/dashboard.blade.php`.
4. Agregar la ruta:
   ```php
   Route::get('/supervisor/dashboard', [SupervisorController::class, 'dashboard'])
       ->middleware(['auth', 'rol:Supervisor'])
       ->name('supervisor.dashboard');
   ```
5. Asignar permisos desde el panel admin.

No hay que tocar el `AuthController`: `redirectByRole()` construye la ruta como
`strtolower($rol) . '.dashboard'` (con `admin.dashboard` como único alias, para Administrador).

## Agregar un permiso nuevo

1. Insertar en `permisos` (`nombre` en snake_case).
2. Asignarlo a los roles que correspondan desde el panel.
3. Proteger la ruta: `->middleware(['auth', 'permission:exportar_reportes'])`.
4. Usarlo en Blade: `@if (auth()->user()->tienePermiso('exportar_reportes'))`.

## Comandos útiles

```bash
php artisan migrate:fresh --seed
php artisan config:clear && php artisan route:clear && php artisan view:clear
```

## Notas de este proyecto

- `config/auth.php` apunta a `App\Models\Cuenta::class`. Si defines `AUTH_MODEL` en el `.env`,
  ese valor gana: no lo definas, o ponlo en `App\Models\Cuenta`.
- La migración por defecto `0001_01_01_000000_create_users_table.php` se conservó porque
  **también crea la tabla `sessions`**, que este proyecto necesita (`SESSION_DRIVER=database`).
  La tabla `users` queda sin uso; `App\Models\User` ya no participa en la autenticación.

## Troubleshooting

### "Class App\Models\Cuenta does not exist"
Verifica `config/auth.php` → `providers.users.model` y que exista `app/Models/Cuenta.php`.

### El login dice "Contraseña incorrecta" con una cuenta nueva
El `password` debe estar guardado con `Hash::make()`, nunca en texto plano.

### Los middlewares `rol` y `permission` no se reconocen
Revisa `bootstrap/app.php` → `$middleware->alias([...])` y ejecuta `php artisan route:clear`.

### Un usuario entra pero lo devuelve al login
Su rol no tiene una ruta `<rol>.dashboard` registrada. Crea el controlador, la vista y la ruta
(ver *Agregar un rol nuevo*).
