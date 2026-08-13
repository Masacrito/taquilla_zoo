<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    /**
     * Contraseña temporal del Super Admin.
     * TODO: cambiar al primer login desde el panel admin.
     */
    private const RESET_PASSWORD_DEFAULT = 'admin123';

    public function run(): void
    {
        // === Roles internos ===
        // Solo hay dos roles en esta tabla. El visitante NO es un rol: vive en
        // la tabla `clientes` con su propio guard (`cliente`), para que nunca
        // pueda alcanzar /admin/*.
        //
        // El id_rol = 1 DEBE ser Administrador: el Super Admin se identifica
        // por id_usuario = 1 y las protecciones del AdminController comparan
        // contra id_rol === 1.
        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador', 'created_at' => now(), 'updated_at' => now()],
            ['id_rol' => 2, 'nombre' => 'Taquilla',      'created_at' => now(), 'updated_at' => now()],
        ]);

        // === Permisos base ===
        DB::table('permisos')->insert([
            ['id_permiso' => 1, 'nombre' => 'gestion_usuarios',  'descripcion' => 'Ver listado de usuarios',         'created_at' => now(), 'updated_at' => now()],
            ['id_permiso' => 2, 'nombre' => 'crear_usuarios',    'descripcion' => 'Crear nuevos usuarios',           'created_at' => now(), 'updated_at' => now()],
            ['id_permiso' => 3, 'nombre' => 'editar_usuarios',   'descripcion' => 'Editar datos de usuarios',        'created_at' => now(), 'updated_at' => now()],
            ['id_permiso' => 4, 'nombre' => 'eliminar_usuarios', 'descripcion' => 'Eliminar usuarios',               'created_at' => now(), 'updated_at' => now()],
            ['id_permiso' => 5, 'nombre' => 'cambiar_roles',     'descripcion' => 'Cambiar rol de un usuario',       'created_at' => now(), 'updated_at' => now()],
            ['id_permiso' => 6, 'nombre' => 'activar_cuentas',   'descripcion' => 'Activar/desactivar cuentas',      'created_at' => now(), 'updated_at' => now()],
            ['id_permiso' => 7, 'nombre' => 'gestion_permisos',  'descripcion' => 'Editar permisos asignados a rol', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // === Asignación: Administrador recibe los 7 ===
        // Taquilla no recibe ninguno de estos: la administración de cuentas es
        // exclusiva de Administrador. Sus permisos operativos los siembra
        // PermisosTaquillaSeeder.
        foreach (range(1, 7) as $idPermiso) {
            DB::table('rol_permiso')->insert([
                'id_rol'     => 1,
                'id_permiso' => $idPermiso,
            ]);
        }

        // === Super Admin (id_usuario = 1) ===
        // El segundo argumento es obligatorio: la PK no se llama `id`.
        // Sin él, Postgres recibe `returning "id"` y truena.
        $usuarioId = DB::table('usuarios')->insertGetId([
            'nombre'     => 'Super Admin',
            'puesto'     => 'Administrador del sistema',
            'email'      => 'admin@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuario');

        DB::table('cuentas')->insert([
            'username'   => 'admin',
            'password'   => Hash::make(self::RESET_PASSWORD_DEFAULT),
            'estado'     => 'activo',
            'id_usuario' => $usuarioId,
            'id_rol'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->resincronizarSecuencias();
    }

    /**
     * PostgreSQL no avanza la secuencia cuando el INSERT trae el id explícito
     * (a diferencia de MySQL, que sí mueve el AUTO_INCREMENT). Sin esto, el
     * siguiente insert sin id intenta reusar el 1 y viola la PK.
     *
     * No-op en cualquier otro driver.
     */
    private function resincronizarSecuencias(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tablas = [
            'roles'    => 'id_rol',
            'permisos' => 'id_permiso',
            'usuarios' => 'id_usuario',
            'cuentas'  => 'id_cuenta',
        ];

        foreach ($tablas as $tabla => $pk) {
            DB::statement(
                "SELECT setval(
                    pg_get_serial_sequence(?, ?),
                    COALESCE((SELECT MAX({$pk}) FROM {$tabla}), 0) + 1,
                    false
                )",
                [$tabla, $pk]
            );
        }
    }
}
