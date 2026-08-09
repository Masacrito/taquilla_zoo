<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class Cuenta extends Authenticatable
{
    use Notifiable;

    protected $table = 'cuentas';
    protected $primaryKey = 'id_cuenta';

    protected $fillable = [
        'username', 'password', 'estado', 'id_usuario', 'id_rol',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    public function isAdmin(): bool
    {
        return optional($this->rol)->nombre === 'Administrador';
    }

    public function isTaquilla(): bool
    {
        return optional($this->rol)->nombre === 'Taquilla';
    }

    public function isVisitante(): bool
    {
        return optional($this->rol)->nombre === 'Visitante';
    }

    /**
     * Verificador genérico para cualquier rol custom.
     * Case-insensitive.
     */
    public function tieneRol(string $nombreRol): bool
    {
        return strtolower(optional($this->rol)->nombre ?? '') === strtolower($nombreRol);
    }

    public function tienePermiso(string $permiso): bool
    {
        return DB::table('rol_permiso')
            ->join('permisos', 'rol_permiso.id_permiso', '=', 'permisos.id_permiso')
            ->where('rol_permiso.id_rol', $this->id_rol)
            ->where('permisos.nombre', $permiso)
            ->exists();
    }

    public function permisosArray(): array
    {
        return DB::table('rol_permiso')
            ->where('id_rol', $this->id_rol)
            ->pluck('id_permiso')
            ->map(fn($v) => (int) $v)
            ->toArray();
    }

    public function actualizarPermisos(array $nuevosPermisos): bool
    {
        DB::table('rol_permiso')->where('id_rol', $this->id_rol)->delete();
        foreach ($nuevosPermisos as $idPermiso) {
            DB::table('rol_permiso')->insert([
                'id_rol'     => $this->id_rol,
                'id_permiso' => (int) $idPermiso,
            ]);
        }
        return true;
    }

    // === Helpers semánticos (azúcar sintáctico) ===
    public function puedeGestionarUsuarios(): bool  { return $this->tienePermiso('gestion_usuarios'); }
    public function puedeCrearUsuarios(): bool      { return $this->tienePermiso('crear_usuarios'); }
    public function puedeEditarUsuarios(): bool     { return $this->tienePermiso('editar_usuarios'); }
    public function puedeEliminarUsuarios(): bool   { return $this->tienePermiso('eliminar_usuarios'); }
    public function puedeCambiarRoles(): bool       { return $this->tienePermiso('cambiar_roles'); }
    public function puedeActivarCuentas(): bool     { return $this->tienePermiso('activar_cuentas'); }
    public function puedeGestionarPermisos(): bool  { return $this->tienePermiso('gestion_permisos'); }
}
