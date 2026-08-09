<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaquillaController;
use App\Http\Controllers\VisitanteController;
use Illuminate\Support\Facades\Route;

// === LOGIN ===
Route::get('/',  [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')->middleware('auth');

// === DASHBOARDS POR ROL ===
Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])
    ->middleware(['auth', 'rol:Administrador'])
    ->name('admin.dashboard');

Route::get('/taquilla/dashboard', [TaquillaController::class, 'dashboard'])
    ->middleware(['auth', 'rol:Taquilla'])
    ->name('taquilla.dashboard');

Route::get('/visitante/dashboard', [VisitanteController::class, 'dashboard'])
    ->middleware(['auth', 'rol:Visitante'])
    ->name('visitante.dashboard');

// Plantilla para roles adicionales:
// Route::get('/supervisor/dashboard', [SupervisorController::class, 'dashboard'])
//     ->middleware(['auth', 'rol:Supervisor'])
//     ->name('supervisor.dashboard');

// === ADMIN: gestión de usuarios (por PERMISO, no por rol) ===
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
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
});
