<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rubros de cobro (brief §5.2).
 *
 * `precio_centavos` es INTEGER a propósito (brief §4.1): el dinero se guarda
 * en centavos, nunca en float ni decimal, para que los cortes cuadren al
 * centavo. 4000 = $40.00.
 *
 * El precio de una compra JAMÁS se lee de aquí en el momento del corte: se
 * congela en compra_detalle.precio_centavos_snap al comprar (§4.3).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('rubros', function (Blueprint $table) {
            $table->id();

            $table->string('tipo', 80);              // descripción corta
            $table->text('descripcion')->nullable(); // descripción a detalle

            $table->foreignId('id_nacionalidad')->constrained('nacionalidades')->restrictOnDelete();
            $table->foreignId('id_subnacionalidad')->constrained('subnacionalidades')->restrictOnDelete();
            $table->foreignId('id_tipo_acceso')->constrained('tipos_acceso')->restrictOnDelete();

            $table->integer('precio_centavos');

            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['activo', 'vigente_desde', 'vigente_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubros');
    }
};
