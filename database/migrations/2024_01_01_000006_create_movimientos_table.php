<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->bigIncrements('id_movimiento');
            $table->unsignedBigInteger('id_cuenta')->nullable();
            $table->string('tabla', 80);
            $table->string('accion', 20);
            $table->string('registro_id', 60)->nullable();
            $table->json('detalles')->nullable();
            $table->timestampTz('fecha')->useCurrent();

            $table->foreign('id_cuenta')
                  ->references('id_cuenta')->on('cuentas')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
