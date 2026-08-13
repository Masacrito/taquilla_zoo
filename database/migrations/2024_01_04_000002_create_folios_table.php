<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador de folios (brief §4.6: consecutivos, sin huecos, nunca reutilizados).
 *
 * No se usa una secuencia de PostgreSQL a propósito: las secuencias NO se
 * revierten al hacer rollback, así que dejarían huecos en la numeración.
 *
 * En su lugar, esta tabla se bloquea con SELECT ... FOR UPDATE dentro de la
 * transacción de compra. Si la transacción falla, el número no se consume;
 * si tiene éxito, avanza exactamente uno. El bloqueo serializa las compras
 * concurrentes, que es justo lo que exige "sin huecos".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('folios', function (Blueprint $table) {
            $table->string('serie', 20)->primary();
            $table->unsignedBigInteger('ultimo')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
