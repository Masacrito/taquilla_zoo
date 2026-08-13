<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de verificación de correo (brief §5.3).
 *
 * Se guarda el HASH del código, nunca el código: si alguien lee la tabla,
 * no puede verificar cuentas ajenas. Vigencia 10 minutos, máximo 3 reenvíos.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('verificaciones_correo', function (Blueprint $table) {
            $table->id();
            $table->string('correo', 160)->index();
            $table->string('codigo_hash');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestampTz('expira_en');
            $table->timestampTz('consumido_en')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verificaciones_correo');
    }
};
