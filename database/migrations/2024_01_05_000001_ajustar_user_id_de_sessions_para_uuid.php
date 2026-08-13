<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sessions.user_id` tiene que aceptar UUID.
 *
 * La migración por defecto de Laravel lo declara como BIGINT porque asume que
 * los usuarios tienen id autoincremental. Aquí conviven dos poblaciones:
 *
 *   cuentas  → id_cuenta BIGINT   (personal interno)
 *   clientes → id UUID            (visitantes, brief §5.3)
 *
 * Cuando un cliente inicia sesión, el manejador de sesiones en base de datos
 * escribe su identificador en esta columna y PostgreSQL rechaza el UUID:
 *
 *   SQLSTATE[22P02]: invalid input syntax for type bigint
 *
 * Se pasa a VARCHAR, que admite las dos formas. No hay llave foránea que
 * romper: la columna nunca la tuvo.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // USING explícito: PostgreSQL no convierte bigint a varchar solo.
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE VARCHAR(64) USING user_id::VARCHAR');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE sessions MODIFY user_id VARCHAR(64) NULL');
        }

        // SQLite es de tipado dinámico: guarda el UUID en la columna sin
        // quejarse, y no soporta ALTER COLUMN. No hace falta hacer nada.
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        // Vaciar primero: un UUID no cabe de vuelta en un bigint.
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->update(['user_id' => null]);
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE BIGINT USING user_id::BIGINT');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE sessions MODIFY user_id BIGINT UNSIGNED NULL');
        }
    }
};
