<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthSeeder extends Seeder
{
    /**
     * Contraseña inicial del Super Admin.
     *
     * NO puede estar escrita en el código. Estaba fija en 'admin123' y este
     * repositorio es público: cualquiera que encontrara el sitio desplegado
     * podía entrar al panel con `admin` y esa contraseña, porque además no
     * hay rotación forzada al primer ingreso.
     *
     * Ahora se genera al azar y se imprime UNA sola vez al sembrar. Para
     * despliegues automatizados se puede fijar con ADMIN_PASSWORD_INICIAL.
     *
     * Sin símbolos a propósito: la contraseña se copia a mano desde una
     * terminal y acaba en archivos .env, donde comillas y `$` dan problemas.
     */
    private function passwordInicial(): string
    {
        $fijada = env('ADMIN_PASSWORD_INICIAL');

        return filled($fijada)
            ? (string) $fijada
            : Str::password(16, symbols: false);
    }

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

        $password = $this->passwordInicial();

        DB::table('cuentas')->insert([
            'username'   => 'admin',
            'password'   => Hash::make($password),
            'estado'     => 'activo',
            'id_usuario' => $usuarioId,
            'id_rol'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->resincronizarSecuencias();

        // Se imprime UNA vez. No queda en ningún lado más: ni en el código,
        // ni en la base en claro, ni en la bitácora.
        $this->command?->newLine();
        $this->command?->warn("  Super Admin creado —  usuario: admin  |  contraseña: {$password}");
        $this->command?->warn('  Anótala AHORA: no se vuelve a mostrar. Cámbiala al primer ingreso.');
        $this->command?->newLine();
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
