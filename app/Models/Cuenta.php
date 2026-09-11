<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class Cuenta extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

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

    /**
     * El Super Admin es el usuario 1, el que siembra AuthSeeder.
     *
     * Vivía como constante privada dentro de AdminController, donde solo esa
     * clase podía consultarlo. Se subió aquí porque la notificación de fallos
     * del sistema también necesita saber quién es, y dos definiciones de
     * «quién manda» acaban desincronizándose.
     */
    public const SUPER_ADMIN_ID = 1;

    public function esSuperAdmin(): bool
    {
        return (int) $this->id_usuario === self::SUPER_ADMIN_ID;
    }

    /** La cuenta del Super Admin, o null si todavía no existe. */
    public static function superAdmin(): ?self
    {
        return static::with('usuario')
            ->where('id_usuario', self::SUPER_ADMIN_ID)
            ->first();
    }

    public function isAdmin(): bool
    {
        return optional($this->rol)->nombre === 'Administrador';
    }

    public function isTaquilla(): bool
    {
        return optional($this->rol)->nombre === 'Taquilla';
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
