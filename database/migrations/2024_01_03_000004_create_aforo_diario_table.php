<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendario de operación: qué días abre el zoológico.
 *
 * NO lleva cupo. El área operativa definió que no hay aforo máximo ni mínimo:
 * pueden entrar tres personas o mil, y el visitante compra los boletos que
 * quiera. Por eso aquí no existen `cupo_maximo` ni `reservados`, y ninguna
 * compra reserva lugares.
 *
 * Lo que sí decide esta tabla es la fecha: los lunes se generan cerrados
 * porque el ZooMAT no abre, y un día puede cerrarse por contingencia.
 * El resto de la semana opera de 8:30 a 16:00.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('aforo_diario', function (Blueprint $table) {
            $table->date('fecha')->primary();
            $table->boolean('cerrado')->default(false);
            $table->string('motivo_cierre', 160)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aforo_diario');
    }
};
