<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos que clasifican al visitante y determinan la tarifa (brief §5.1).
 */
return new class extends Migration {
    public function up(): void
    {
        // NACIONAL, EXTRANJERO
        Schema::create('nacionalidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });

        // ADULTO NACIONAL, ADULTO EXTRANJERO, NIÑO NACIONAL, NIÑO EXTRANJERO
        Schema::create('subnacionalidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });

        // PAGO NORMAL, GRATIS
        Schema::create('tipos_acceso', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });

        // Previsto en el anteproyecto, sin operación todavía (brief §5.1).
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones');
        Schema::dropIfExists('tipos_acceso');
        Schema::dropIfExists('subnacionalidades');
        Schema::dropIfExists('nacionalidades');
    }
};
