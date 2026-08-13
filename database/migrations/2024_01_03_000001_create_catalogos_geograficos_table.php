<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos geográficos (brief §5.1).
 *
 * La procedencia se captura por renglón de compra, no por compra, porque un
 * mismo comprador puede traer gente de distintos lugares.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('paises', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('iso', 3)->nullable()->unique();
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });

        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_estado')->constrained('estados')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->unique(['id_estado', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
        Schema::dropIfExists('estados');
        Schema::dropIfExists('paises');
    }
};
