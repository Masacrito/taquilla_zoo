<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitantes del portal público (brief §5.3).
 *
 * NO son cuentas internas: viven en su propia tabla, con su propio guard
 * (`cliente`), para que nunca puedan alcanzar /admin/*.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('correo', 160)->unique();
            $table->string('password')->nullable();          // null si entró por OAuth
            $table->string('proveedor_oauth', 40)->nullable();     // 'google' | null
            $table->string('proveedor_oauth_id', 120)->nullable();

            $table->string('nombre', 120);
            $table->string('apellidos', 160);
            $table->date('fecha_nacimiento');
            $table->string('genero', 40);
            $table->string('telefono', 30);

            // FK a `paises` y `estados`, que se crean en Fase 1 (catálogos).
            // Por eso hoy van como columnas sueltas: la restricción se agrega
            // en una migración posterior, cuando existan las tablas destino.
            $table->unsignedBigInteger('id_pais')->nullable();
            $table->unsignedBigInteger('id_estado')->nullable();

            $table->timestampTz('correo_verificado_en')->nullable();

            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();

            // Un mismo proveedor no puede tener dos veces la misma cuenta externa.
            $table->unique(['proveedor_oauth', 'proveedor_oauth_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
