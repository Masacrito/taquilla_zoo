<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cupo por día (brief §5.6).
 *
 * `reservados` se incrementa y decrementa SIEMPRE con una sentencia
 * condicional atómica (brief §4.5), nunca con SELECT + UPDATE:
 *
 *   UPDATE aforo_diario SET reservados = reservados + :n
 *   WHERE fecha = :f AND cerrado = false AND reservados + :n <= cupo_maximo
 *
 * Si afecta 0 filas, no había lugar. Sin esto, dos compras simultáneas
 * sobrevenden el cupo.
 *
 * El CHECK de la base es la última línea de defensa por si alguien escribe
 * un UPDATE a mano.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('aforo_diario', function (Blueprint $table) {
            $table->date('fecha')->primary();
            $table->integer('cupo_maximo');
            $table->integer('reservados')->default(0);
            $table->boolean('cerrado')->default(false);
            $table->string('motivo_cierre', 160)->nullable();
            $table->timestampsTz();
        });

        // SQLite (usado en las pruebas) no soporta ALTER TABLE ADD CONSTRAINT.
        // La garantía real la da el UPDATE condicional del servicio; esto es
        // defensa en profundidad para el entorno real.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE aforo_diario ADD CONSTRAINT aforo_reservados_no_negativos CHECK (reservados >= 0)');
            DB::statement('ALTER TABLE aforo_diario ADD CONSTRAINT aforo_reservados_dentro_del_cupo CHECK (reservados <= cupo_maximo)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aforo_diario');
    }
};
