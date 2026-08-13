<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AforoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteAdminController;
use App\Http\Controllers\ClienteAuthController;
use App\Http\Controllers\CompraAdminController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PagoSimuladoController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\RubroController;
use App\Http\Controllers\TaquillaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PORTAL PÚBLICO — guard `cliente`
|--------------------------------------------------------------------------
|
| Los visitantes viven en la tabla `clientes` y no tienen rol ni permisos.
| `guard.exclusivo:cliente` impide que una cuenta interna compre (brief §3.2).
|
*/

Route::get('/', [PortalController::class, 'inicio'])->name('portal.inicio');

Route::middleware('guard.exclusivo:cliente')->group(function () {
    Route::get('/registro',  [ClienteAuthController::class, 'mostrarRegistro'])->name('portal.registro');
    Route::post('/registro', [ClienteAuthController::class, 'registrar']);

    Route::get('/registro/verificar',  [ClienteAuthController::class, 'mostrarVerificacion'])->name('portal.verificar');
    Route::post('/registro/verificar', [ClienteAuthController::class, 'verificar']);
    Route::post('/registro/reenviar',  [ClienteAuthController::class, 'reenviar'])
        ->middleware('throttle:3,10')       // máx 3 reenvíos cada 10 minutos (§5.3)
        ->name('portal.reenviar');

    Route::get('/ingresar',  [ClienteAuthController::class, 'mostrarIngreso'])->name('portal.ingresar');
    Route::post('/ingresar', [ClienteAuthController::class, 'ingresar'])->middleware('throttle:10,1');

    // Google OAuth. El controlador responde 404 si no hay credenciales
    // configuradas, así que el portal funciona igual sin ellas.
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirigir'])->name('portal.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

    // Google no entrega fecha de nacimiento, género ni teléfono: se piden aquí
    // antes de crear el registro, para no relajar el esquema de `clientes`.
    Route::get('/registro/completar',  [GoogleAuthController::class, 'mostrarCompletar'])->name('portal.completar');
    Route::post('/registro/completar', [GoogleAuthController::class, 'completar']);
});

Route::post('/salir', [ClienteAuthController::class, 'salir'])->name('portal.salir');

// === COMPRA (requiere cliente autenticado) ===
// `guard.exclusivo` va PRIMERO a propósito: si corriera después de `auth`,
// una cuenta interna recibiría un redirect al login en vez del 403 que exige
// el brief §10.6, porque `auth:cliente` cortaría antes.
Route::middleware(['guard.exclusivo:cliente', 'auth:cliente'])->group(function () {
    Route::get('/comprar',         [CompraController::class, 'crear'])->name('compras.crear');
    Route::post('/comprar/cotizar', [CompraController::class, 'cotizar'])->name('compras.cotizar');
    Route::post('/comprar',        [CompraController::class, 'guardar'])->name('compras.guardar');

    Route::get('/comprar/retorno/{folio}', [CompraController::class, 'retorno'])->name('compras.retorno');

    Route::get('/mis-compras',              [CompraController::class, 'index'])->name('compras.index');
    Route::get('/mis-compras/{folio}',      [CompraController::class, 'ver'])->name('compras.ver');
    Route::get('/mis-compras/{folio}/qr',   [CompraController::class, 'qr'])->name('compras.qr');
});

// === PASARELA SIMULADA (solo local/testing; el controlador se apaga solo) ===
Route::get('/pago-simulado/{referencia}',  [PagoSimuladoController::class, 'mostrar'])->name('pago.simulado');
Route::post('/pago-simulado/{referencia}', [PagoSimuladoController::class, 'confirmar'])->name('pago.simulado.confirmar');

/*
|--------------------------------------------------------------------------
| PANEL INTERNO — guard `web`
|--------------------------------------------------------------------------
*/

// === LOGIN DEL PERSONAL ===
Route::get('/acceso',  [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/acceso', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')->middleware('auth');

// === DASHBOARDS POR ROL (guard `web`: solo personal interno) ===
// El visitante no entra por aquí: usa el guard `cliente` sobre la tabla
// `clientes` y su portal vive en las rutas públicas.
Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])
    ->middleware(['guard.exclusivo:web', 'auth', 'account.status', 'rol:Administrador'])
    ->name('admin.dashboard');

Route::get('/taquilla/dashboard', [TaquillaController::class, 'dashboard'])
    ->middleware(['guard.exclusivo:web', 'auth', 'account.status', 'rol:Taquilla'])
    ->name('taquilla.dashboard');

// Plantilla para roles internos adicionales:
// Route::get('/supervisor/dashboard', [SupervisorController::class, 'dashboard'])
//     ->middleware(['auth', 'rol:Supervisor'])
//     ->name('supervisor.dashboard');

// === ADMIN: gestión de usuarios (por PERMISO, no por rol) ===
Route::prefix('admin')->name('admin.')->middleware(['guard.exclusivo:web', 'auth', 'account.status'])->group(function () {
    Route::get('/users',  [AdminController::class, 'usersIndex'])
        ->middleware('permission:any,gestion_usuarios,crear_usuarios,editar_usuarios')
        ->name('users.index');

    Route::post('/users', [AdminController::class, 'storeUser'])
        ->middleware('permission:crear_usuarios')
        ->name('users.store');

    Route::put('/users/{id}', [AdminController::class, 'updateUser'])
        ->middleware('permission:editar_usuarios')
        ->name('users.update');

    Route::put('/users/{id}/update-role', [AdminController::class, 'updateUserRole'])
        ->middleware('permission:cambiar_roles')
        ->name('users.update-role');

    Route::put('/users/{id}/update-permissions', [AdminController::class, 'updateUserPermissions'])
        ->middleware('permission:gestion_permisos')
        ->name('users.update-permissions');

    Route::put('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus'])
        ->middleware('permission:activar_cuentas')
        ->name('users.toggle-status');

    Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])
        ->middleware('permission:eliminar_usuarios')
        ->name('users.destroy');

    // === RUBROS DE COBRO ===
    Route::get('/rubros', [RubroController::class, 'index'])
        ->middleware('permission:any,gestion_rubros,editar_rubros')
        ->name('rubros.index');

    Route::post('/rubros', [RubroController::class, 'store'])
        ->middleware('permission:editar_rubros')
        ->name('rubros.store');

    Route::put('/rubros/{rubro}', [RubroController::class, 'update'])
        ->middleware('permission:editar_rubros')
        ->name('rubros.update');

    Route::put('/rubros/{rubro}/toggle', [RubroController::class, 'toggle'])
        ->middleware('permission:editar_rubros')
        ->name('rubros.toggle');

    Route::delete('/rubros/{rubro}', [RubroController::class, 'destroy'])
        ->middleware('permission:editar_rubros')
        ->name('rubros.destroy');

    // === CATÁLOGOS ===
    Route::get('/catalogos/{catalogo?}', [CatalogoController::class, 'index'])
        ->middleware('permission:any,gestion_catalogos,editar_catalogos')
        ->name('catalogos.index');

    Route::post('/catalogos/{catalogo}', [CatalogoController::class, 'store'])
        ->middleware('permission:editar_catalogos')
        ->name('catalogos.store');

    Route::put('/catalogos/{catalogo}/{id}', [CatalogoController::class, 'update'])
        ->middleware('permission:editar_catalogos')
        ->name('catalogos.update');

    Route::put('/catalogos/{catalogo}/{id}/toggle', [CatalogoController::class, 'toggle'])
        ->middleware('permission:editar_catalogos')
        ->name('catalogos.toggle');

    // === AFORO DIARIO ===
    Route::get('/aforo', [AforoController::class, 'index'])
        ->middleware('permission:gestion_aforo')
        ->name('aforo.index');

    Route::post('/aforo/generar', [AforoController::class, 'generar'])
        ->middleware('permission:gestion_aforo')
        ->name('aforo.generar');

    Route::put('/aforo/{fecha}', [AforoController::class, 'update'])
        ->middleware('permission:gestion_aforo')
        ->name('aforo.update');

    // === VISITANTES Y SUS COMPRAS ===
    Route::get('/clientes', [ClienteAdminController::class, 'index'])
        ->middleware('permission:gestion_clientes')
        ->name('clientes.index');

    Route::get('/clientes/{cliente}', [ClienteAdminController::class, 'ver'])
        ->middleware('permission:gestion_clientes')
        ->name('clientes.ver');

    Route::get('/compras', [CompraAdminController::class, 'index'])
        ->middleware('permission:gestion_clientes')
        ->name('compras.index');

    Route::get('/compras/{compra}', [CompraAdminController::class, 'ver'])
        ->middleware('permission:gestion_clientes')
        ->name('compras.ver');

    Route::put('/compras/{compra}/cancelar', [CompraAdminController::class, 'cancelar'])
        ->middleware('permission:cancelar_compras')
        ->name('compras.cancelar');

    // === BITÁCORA DE AUDITORÍA ===
    Route::get('/bitacora', [BitacoraController::class, 'index'])
        ->middleware('permission:ver_bitacora_auditoria')
        ->name('bitacora.index');
});
