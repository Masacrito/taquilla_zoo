# Sistema de autenticación y RBAC

## Stack

- Laravel 12 (middlewares registrados en `bootstrap/app.php`)
- PostgreSQL 16
- Login por **username** (no email)
- Roles internos: **Administrador**, **Taquilla**
- 21 permisos granulares vía tabla pivote `rol_permiso` (7 base + 14 operativos)
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

El seeder solo crea al Super Admin. Las cuentas de **Taquilla** se crean desde el panel
(`/admin/users` → *+ Nuevo usuario*).

> El **visitante no es un rol de este sistema**: vive en la tabla `clientes` con su propio
> guard (`cliente`) y nunca debe alcanzar `/admin/*`. Ver el brief `taquilla.md` §3.2.

## Los dos guards

```
web      → provider `cuentas`  → App\Models\Cuenta   (personal: Administrador, Taquilla)
cliente  → provider `clientes` → App\Models\Cliente  (visitantes del portal)
```

Son sesiones independientes: estar autenticado en uno no da nada en el otro.

| | `web` | `cliente` |
|---|---|---|
| Login | `username` + password | `correo` + password, o Google |
| Alta | la crea un Administrador | auto-registro con verificación |
| Roles y permisos | sí | no, todos iguales |
| Estado activo/inactivo | sí | no |

El aislamiento lo aplica `guard.exclusivo`, **antes** de `auth`:

```php
->middleware(['guard.exclusivo:web', 'auth', 'account.status', 'rol:Administrador'])
```

El orden importa: si `auth` corriera primero, un cliente sería redirigido al login en vez de
recibir 403, y la prueba §10.6 del brief no se cumpliría. En Fase 2, el portal público usará
el espejo `guard.exclusivo:cliente`.

`account.status` ya **no** está en el grupo `web` global — se aplica por ruta, solo a las
internas, porque el brief §3.2 excluye el portal público. El middleware además consulta
`Auth::guard('web')` de forma explícita.

> Nota: un volcado de `config('auth.providers')` muestra también un provider `users`. No está
> en `config/auth.php`: Laravel 11+ fusiona los defaults del framework. Ningún guard lo
> referencia, así que es inalcanzable.

## Estructura de rutas

| Ruta                                  | Protección                                              |
|---------------------------------------|---------------------------------------------------------|
| `/`                                   | login (GET muestra el form, POST autentica)             |
| `/admin/dashboard`                    | `rol:Administrador`                                     |
| `/taquilla/dashboard`                 | `rol:Taquilla`                                          |
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

Estos 7 son **exclusivos de Administrador**: Taquilla no administra cuentas.

## Permisos operativos (`PermisosTaquillaSeeder`)

Los siembra un seeder aparte, sin tocar `AuthSeeder` (brief §3.3). Es idempotente.

| permiso                  | Administrador | Taquilla |
|--------------------------|:---:|:---:|
| `gestion_rubros`         | ✓ |   |
| `editar_rubros`          | ✓ |   |
| `gestion_catalogos`      | ✓ |   |
| `editar_catalogos`       | ✓ |   |
| `gestion_aforo`          | ✓ |   |
| `gestion_clientes`       | ✓ |   |
| `validar_accesos`        | ✓ | ✓ |
| `ver_bitacora_accesos`   | ✓ | ✓ |
| `generar_cortes`         | ✓ | ✓ |
| `conciliar_pagos`        | ✓ |   |
| `ver_estadisticas`       | ✓ |   |
| `cancelar_compras`       | ✓ |   |
| `autorizar_reembolsos`   | ✓ |   |
| `ver_bitacora_auditoria` | ✓ |   |

Los permisos son **por rol, no por cuenta**: marcarlos desde la fila de una cuenta de Taquilla
los aplica a todas las cuentas con ese rol.

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
`tieneRol()`, `tienePermiso()`, `puedeGestionarUsuarios()`,
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

## Eliminación de cuentas

Es **lógica** (brief §4.6). `destroyUser()` marca `deleted_at` en `cuentas` y `usuarios`; la fila
nunca sale de la base.

- La cuenta deja de poder autenticarse: `SoftDeletes` excluye los registros borrados de toda
  consulta, incluida la del provider de auth.
- Desaparece del listado del panel.
- El asiento en `movimientos` conserva username, rol y nombre.
- Para consultarlas: `Cuenta::withTrashed()` / `Cuenta::onlyTrashed()`.

> El **username no se reutiliza**: el índice único incluye las filas borradas. Es deliberado,
> para que la bitácora no quede ambigua. El formulario de alta lo explica en el mensaje de error.

## Zona horaria

`config/app.php` tenía `'timezone' => 'UTC'` **hardcodeado** — poner `APP_TIMEZONE` en el `.env`
no surtía efecto. Ahora lee `env('APP_TIMEZONE', 'America/Mexico_City')`.

Importa porque el aforo se indexa por fecha: en UTC, una visita de las 18:30 hora local se
registraría al día siguiente.

Las tablas propias del proyecto usan `timestamptz`. Las de Laravel (`users`,
`password_reset_tokens`, `failed_jobs`) se dejaron como vienen.

## Correo saliente

El sistema manda dos correos, ambos **en cola**:

| Correo | Cuándo | Contenido |
|---|---|---|
| Código de verificación | Al registrarse o reenviar | 6 dígitos, vigencia 10 min |
| Comprobante de compra | Solo al confirmarse el pago por webhook | QR incrustado + PDF adjunto |

Requiere un worker corriendo:

```bash
php artisan queue:work
```

Sin worker, los correos se encolan y nunca salen. En desarrollo, con
`MAIL_MAILER=log`, el contenido aparece en `storage/logs/laravel.log`.

### Configurar un SMTP real

Estas variables son lo único que hay que cambiar; el código no se toca:

```
MAIL_MAILER=smtp
MAIL_HOST=<servidor>
MAIL_PORT=587
MAIL_USERNAME=<usuario>
MAIL_PASSWORD=<contraseña>
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=taquilla@<dominio>
MAIL_FROM_NAME="ZooMAT"
```

Opciones, de más a menos recomendable para un sistema de gobierno:

1. **SMTP institucional** del Gobierno de Chiapas. Los correos salen de un
   dominio oficial, lo que mejora mucho la entregabilidad.
2. **Servicio transaccional** (Resend, Postmark, Amazon SES). Hechos para
   esto, con métricas de rebote y entrega.
3. **Gmail**. Funciona, pero exige una *contraseña de aplicación* (no la del
   correo) y limita a ~500 envíos diarios: insuficiente para venta en línea.

> Configura SPF, DKIM y DMARC en el dominio que uses. Sin eso, los
> comprobantes acaban en spam y los visitantes llegan al acceso sin su QR.

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
- **Pendiente de Fase 0**: renombrar los providers a `cuentas`/`clientes` y agregar el guard
  `cliente` (brief §3.2). Hoy el provider sigue llamándose `users` aunque ya apunta a `Cuenta`.
- El nombre de la base va en minúsculas (`taquilla_semahn`): PostgreSQL pliega a minúsculas
  los identificadores sin comillas, y `taquilla_SEMAHN` obligaría a citarlo siempre.
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
