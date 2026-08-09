<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    private const SUPER_ADMIN_ID = 1;

    public function dashboard()
    {
        return view('admin.dashboard');
    }

    public function usersIndex()
    {
        $cuentas  = Cuenta::with(['usuario', 'rol'])->orderBy('id_cuenta')->get();
        $roles    = Rol::orderBy('nombre')->get();
        $permisos = Permiso::orderBy('nombre')->get();

        return view('admin.users.index', compact('cuentas', 'roles', 'permisos'));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:120',
            'puesto'   => 'nullable|string|max:120',
            'email'    => 'nullable|email|max:160',
            'username' => 'required|string|max:60|unique:cuentas,username',
            'password' => 'required|string|min:6',
            'id_rol'   => 'required|exists:roles,id_rol',
        ]);

        // Solo Super Admin puede crear Administradores
        if ((int) $request->id_rol === 1 && !$this->esSuperAdmin()) {
            return back()->with('error', 'Solo el Super Administrador puede crear cuentas con rol Administrador.');
        }

        DB::transaction(function () use ($request) {
            $usuario = Usuario::create([
                'nombre' => $request->nombre,
                'puesto' => $request->puesto,
                'email'  => $request->email,
            ]);

            $cuenta = Cuenta::create([
                'username'   => $request->username,
                'password'   => Hash::make($request->password),
                'estado'     => 'activo',
                'id_usuario' => $usuario->id_usuario,
                'id_rol'     => $request->id_rol,
            ]);

            Movimiento::registrar(
                Auth::user()->id_cuenta,
                'cuentas',
                'CREATE',
                (string) $cuenta->id_cuenta,
                ['username' => $cuenta->username, 'id_rol' => (int) $cuenta->id_rol]
            );
        });

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function updateUser(Request $request, $id)
    {
        $cuenta = Cuenta::with('usuario')->findOrFail($id);
        $this->guardSelf($cuenta, 'No puedes editar tu propia cuenta desde aquí.');
        $this->guardSuperAdminTarget($cuenta);

        $request->validate([
            'nombre'   => 'required|string|max:120',
            'puesto'   => 'nullable|string|max:120',
            'email'    => 'nullable|email|max:160',
            'password' => 'nullable|string|min:6',
        ]);

        $cuenta->usuario->update([
            'nombre' => $request->nombre,
            'puesto' => $request->puesto,
            'email'  => $request->email,
        ]);

        $passwordCambiado = false;
        if ($request->filled('password')) {
            $cuenta->password = Hash::make($request->password);
            $cuenta->save();
            $passwordCambiado = true;
        }

        Movimiento::registrar(
            Auth::user()->id_cuenta,
            'cuentas',
            'UPDATE',
            (string) $cuenta->id_cuenta,
            ['nombre' => $request->nombre, 'password_cambiado' => $passwordCambiado]
        );

        return back()->with('success', 'Usuario actualizado.');
    }

    public function updateUserRole(Request $request, $id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $this->guardSelf($cuenta, 'No puedes cambiar tu propio rol.');
        $this->guardSuperAdminTarget($cuenta);

        $request->validate(['id_rol' => 'required|exists:roles,id_rol']);

        // Solo Super Admin puede asignar el rol Administrador
        if ((int) $request->id_rol === 1 && !$this->esSuperAdmin()) {
            return back()->with('error', 'Solo el Super Administrador puede asignar el rol Administrador.');
        }

        $rolAnterior = (int) $cuenta->id_rol;
        $cuenta->id_rol = $request->id_rol;
        $cuenta->save();

        Movimiento::registrar(
            Auth::user()->id_cuenta,
            'cuentas',
            'UPDATE',
            (string) $cuenta->id_cuenta,
            ['id_rol_anterior' => $rolAnterior, 'id_rol_nuevo' => (int) $cuenta->id_rol]
        );

        return back()->with('success', 'Rol actualizado.');
    }

    public function updateUserPermissions(Request $request, $id)
    {
        $cuenta = Cuenta::findOrFail($id);

        // Solo Super Admin puede modificar permisos del rol Administrador
        if ((int) $cuenta->id_rol === 1 && !$this->esSuperAdmin()) {
            return back()->with('error', 'Solo el Super Administrador puede editar los permisos del rol Administrador.');
        }

        $request->validate([
            'permisos'   => 'nullable|array',
            'permisos.*' => 'exists:permisos,id_permiso',
        ]);

        $cuenta->actualizarPermisos($request->permisos ?? []);

        Movimiento::registrar(
            Auth::user()->id_cuenta,
            'rol_permiso',
            'UPDATE',
            (string) $cuenta->id_rol,
            ['permisos' => array_map('intval', $request->permisos ?? [])]
        );

        // Si el usuario logueado editó los permisos de SU propio rol, refrescar sesión
        if ((int) Auth::user()->id_rol === (int) $cuenta->id_rol) {
            session(['permisos_usuario' => Auth::user()->fresh()->permisosArray()]);
        }

        return back()->with('success', 'Permisos actualizados.');
    }

    public function toggleUserStatus($id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $this->guardSelf($cuenta, 'No puedes desactivar tu propia cuenta.');
        $this->guardSuperAdminTarget($cuenta);

        $cuenta->estado = $cuenta->estado === 'activo' ? 'inactivo' : 'activo';
        $cuenta->save();

        Movimiento::registrar(
            Auth::user()->id_cuenta,
            'cuentas',
            'UPDATE',
            (string) $cuenta->id_cuenta,
            ['estado' => $cuenta->estado]
        );

        return back()->with('success', "Cuenta {$cuenta->estado}.");
    }

    public function destroyUser($id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $this->guardSelf($cuenta, 'No puedes eliminar tu propia cuenta.');
        $this->guardSuperAdminTarget($cuenta);

        // No se permite borrar al Super Admin nunca
        if ((int) $cuenta->id_usuario === self::SUPER_ADMIN_ID) {
            return back()->with('error', 'No se puede eliminar al Super Administrador.');
        }

        $snapshot = [
            'username' => $cuenta->username,
            'id_rol'   => (int) $cuenta->id_rol,
        ];
        $idCuentaBorrada = (string) $cuenta->id_cuenta;

        DB::transaction(function () use ($cuenta) {
            $usuarioId = $cuenta->id_usuario;
            $cuenta->delete();
            Usuario::where('id_usuario', $usuarioId)->delete();
        });

        Movimiento::registrar(
            Auth::user()->id_cuenta,
            'cuentas',
            'DELETE',
            $idCuentaBorrada,
            $snapshot
        );

        return back()->with('success', 'Usuario eliminado.');
    }

    // === Guards ===

    private function esSuperAdmin(): bool
    {
        return (int) Auth::user()->id_usuario === self::SUPER_ADMIN_ID;
    }

    private function guardSelf(Cuenta $cuenta, string $msg): void
    {
        if ((int) $cuenta->id_cuenta === (int) Auth::user()->id_cuenta) {
            abort(redirect()->back()->with('error', $msg));
        }
    }

    private function guardSuperAdminTarget(Cuenta $cuenta): void
    {
        // Si el objetivo es otro Administrador (id_rol = 1) y yo NO soy Super Admin → bloquear
        if ((int) $cuenta->id_rol === 1 && !$this->esSuperAdmin()) {
            abort(redirect()->back()->with('error', 'Solo el Super Administrador puede modificar a otros Administradores.'));
        }
    }
}
